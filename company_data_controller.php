<?php

/**
 * @package     Crowley-Hammer20k
 * @author      Gabriel Mioni <gabriel@gabrielmioni.com>
 */

/**
 * This class updates the json.txt file that's responsible for holding company data.
 *
 * The steps are:
 * a. Retrieve company data that's currently at the URL set in company_data_controller::$url.
 * b. Read the data at json.txt. If json.txt doesn't exist, create it using the data collected in (a).
 * c. Create a third new company array by comparing company data from (a) and (b). If a company is present in both (a)
 *    and (b), update its array element with the current price, increment its ['count'] value and update its ['date']
 *    value with the current timestamp.
 * d. If a company is present in (b) but not (a), add it to the new company array.
 * e. Use the new company array to overwrite the data object on json.txt.
 *
 * The class is called in cronjob.php. It is intended to be run once daily. The JSON data object stored on json.txt is
 * used to display an HTML table found at index.php, and created by company_data_viewer.php
 */
class company_data_controller
{
    /** @var string  The URL being scraped for data */
    protected $url;

    /** @var string  HTML retrived by company_data_controller::get_html_via_curl() */
    protected $html;

    /** @var domDocument */
    protected $dom_obj;

    /** @var bool Flag stating whether json.txt is present. */
    protected $is_init = false;

    /** @var array  Array of companies fresh from the HTML at $this->url */
    protected $companies_current;

    /** @var array  Array of companies written to json.txt previously. */
    protected $companies_stored;

    /** @var array  New companies array that will be written to json.txt */
    protected $update_companies;

    public function __construct()
    {
        /* ****************************
         * - Get Current Company data
         * ****************************/
        $this->url  = 'http://investorshub.advfn.com/boards/breakoutboards.aspx';

        $this->html = $this->get_html_via_curl($this->url);

        $this->dom_obj = $this->build_dom_obj($this->html);

        $this->companies_current = $this->get_companies_current($this->dom_obj);

        /* ****************************
         * - Get stored company data
         * ****************************/
        $this->companies_stored = $this->get_companies_stored($this->companies_current);

        $this->purge_old_and_blank_companies($this->companies_stored);

        /* ****************************
         * - Write Updated data
         * ****************************/

        $this->update_companies = $this->build_updated_company_array($this->companies_current, $this->companies_stored, $this->is_init);

        $this->write_companies_updated($this->update_companies);

    }

    /**
     * Does a CURL request to get HTML data that can be parsed.
     *
     * @param $url      string  The URL where data will be retrieved from.
     * @return mixed    string  Returns HTML
     */
    protected function get_html_via_curl($url)
    {
        $session = curl_init();
        curl_setopt($session, CURLOPT_URL, $url);
        curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($session, CURLOPT_CONNECTTIMEOUT, 5);
        $html = curl_exec($session);
        curl_close($session);

        return $html;
    }

    /**
     * Builds a DOM object from which HTML elements can be extracted.
     *
     * @param $html string HTML data.
     * @return domDocument
     */
    protected function build_dom_obj($html)
    {
        $dom = new domDocument;

        libxml_use_internal_errors(true);

        // load the html into the object
        $dom->loadHTML($html);

        // discard white space
        $dom->preserveWhiteSpace = false;

        return $dom;
    }

    /**
     * Extracts tr elements and collects company names from each tr.
     *
     * @param   DOMDocument $dom    Set by $this->build_dom_obj()
     * @return  array               Returns an array of company names.
     */
    protected function get_companies_current(DOMDocument $dom)
    {
        $company_data = array();
        $count = 0;

        $table_rows = $dom->getElementsByTagName('tr');

        foreach ($table_rows as $child_tr)
        {
            if ($count > 0) {

                $tmp = array();

                $html_tr = $child_tr->ownerDocument->saveHTML($child_tr);

                $html_tr = str_replace('"', '', $html_tr);


                $company = $this->extract_company_name($html_tr);
                $price   = $this->extract_price($html_tr);

                if ($company !== '')
                {
                    $tmp['company'] = $company;
                    $tmp['date']    = date('Y-m-d H:i:s');
                    $tmp['count']   = 1;
                    $tmp['price']   = $price;

                    $company_data[] = $tmp;
                }
            }
            ++$count;
        }

        return $company_data;
    }

    /**
     * Searches HTML for the 'title' value in the HTML tr element. Returns value if found.
     *
     * @param $html string The table row being searched
     * @return string If a match is found, return value. Else return whitespace.
     */
    protected function extract_company_name($html)
    {
        if (preg_match('~title=(.*?) href~', $html, $out) === 1) {
            return $out[1];
        }

        return '';
    }

    /**
     * Searches HTML for the price value in the HTML tr element. Returns value if found. Price is wrapped in a
     * span element that always has a CSS ID. The ID always ends 'Last'.
     *
     * @param $html string The table row being searched
     * @return string If a match is found, return value. Else return whitespace.
     */
    protected function extract_price($html)
    {
        if (preg_match('~Last>(.*?)</span>~', $html, $out) === 1) {

            return $out[1];
        }

        return '';
    }

    /**
     * Loops through each $companies_stored array element and unsets elements that are older than a week.
     *
     * @param array $companies_stored
     * @return bool If the check is run on a weekend or Monday, return false. The purge should not operate on those days.
     */
    protected function purge_old_and_blank_companies(array &$companies_stored)
    {
        $day = date('D', time());

        // Don't run the purge on weekend days. The board is not updated.
        $three_day_weekend = array('Sat', 'Sun', 'Mon');
        if (in_array($day, $three_day_weekend))
        {
            return false;
        }

        $count = count($companies_stored);
        $week_ago = time() - (86400 * 2);
        for ($c = 0 ; $c < $count ; ++$c)
        {
            $date    = strtotime($companies_stored[$c]['date']);
            $company = trim($companies_stored[$c]['company']);

            if ($date < $week_ago || $company === '')
            {
                unset($companies_stored[$c]);
            }
        }

        // Reindex array
        $companies_stored = array_values($companies_stored);
    }

    /**
     * Looks for the jason.txt file. If the file is found, reads the file and returns an array from the
     * json_decoded content of the file. If the file doesn't exist, creates it by writing the json_encoded
     * string from $companies.
     *
     * Sets $this->is_init = true if the json.txt is not present.
     *
     * @param array $companies_current
     * @return array If json.txt exists, return the json_decoded array. Else return the $companies array.
     */
    protected function get_companies_stored(array $companies_current)
    {
        $check_file = file_exists('json.txt');

        if (!$check_file)
        {
            // Set the $is_init flag.
            $this->is_init = true;

            $file = @fopen("json.txt","x");

            $json_data = json_encode($companies_current, true);
            fwrite($file, $json_data);
            fclose($file);

            return $companies_current;
        }

        if ($check_file)
        {
            $file = fopen('json.txt', 'r');

            $read = fread($file, filesize('json.txt'));
            fclose($file);

            $json_array = json_decode($read, true);

            return $json_array;
        }
    }

    /**
     * Compares $current_company and $stored_company arrays. Companies that are already in the $stored_company array
     * are updated with new counts/dates if they're also present in the $current_company.
     *
     * @param array $current_company
     * @param array $stored_company
     * @param bool $is_init If true, just returns the $current_company array.
     * @return array
     */
    protected function build_updated_company_array(array $current_company, array $stored_company, $is_init)
    {
        /* If $is_init is true, no need to process data. Just return the $current_company array since that array
         * is necessarily the most recent data. */
        if ($is_init === true)
        {
            return $current_company;
        }

        // Fill array with old records.
        $out_company = $stored_company;

        // Get array of newest company names.
        $current_company_names = $this->return_company_names($current_company);

        // Update old records with new date values
        foreach ($out_company as $key=>$value)
        {
            /*  Check if each stored company is still in the
             *  new company list.
             *  - If it is, update the company array element.
             */
            if (in_array($value['company'], $current_company_names))
            {
                // Get the current price since we need to update the old record.
                $current_co_key = array_search($value['company'], $current_company_names);
                $current_price  = $current_company[$current_co_key]['price'];

                $out_company[$key]['price'] = $current_price;
                $out_company[$key]['date']  = date('Y-m-d H:i:s');
                $out_company[$key]['count'] = $out_company[$key]['count'] + 1;
            }
        }

        // Get array of the stored company names
        $stored_company_names = $this->return_company_names($stored_company);

        // Append new companies that weren't already in the old company array.
        foreach ($current_company as $current)
        {
            if (!in_array($current['company'], $stored_company_names))
            {

                $tmp['company'] = $current['company'];
                $tmp['date']    = date('Y-m-d H:i:s');
                $tmp['count']   = 1;
                $tmp['price']   = $current['price'];

                $out_company[] = $tmp;
            }
        }

        return $out_company;
    }

    /**
     * Returns a 1-dimensional array of company names.
     *
     * @param   array   $company_array The array from which we want to extract company names.
     * @return  array   Array of company names.
     */
    protected function return_company_names(array $company_array)
    {
        $tmp = array();

        foreach ($company_array as $company)
        {
            $tmp[] = $company['company'];
        }
        return $tmp;
    }

    /**
     * Writes new json data to the json.txt file.
     *
     * @param array $update_companies   The updated company array set by $this->build_updated_company_array().
     */
    protected function write_companies_updated(array $update_companies)
    {
        $json_date = json_encode($update_companies);

        $file = fopen("json.txt","w");
        fwrite($file, $json_date);
        fclose($file);
    }
}
