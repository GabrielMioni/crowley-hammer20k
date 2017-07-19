<?php

/**
 * This class updates the json.txt file that's responsible for holding company data.
 *
 * The class compares data previously written to json.txt and current company data extracted from
 * the URL set by company_data_controller::url.   
 */
class company_data_controller
{
    /** @var string  The URL being scraped for data */
    protected $url;

    /** @var string  HTML retrived by investo_matic::get_html_via_curl() */
    protected $html;

    /** @var domDocument * */
    protected $dom_obj;

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

        $this->purge_old_companies($this->companies_current);

        /* ****************************
         * - Get stored company data
         * ****************************/
        $this->companies_stored = $this->get_companies_stored($this->companies_current);

        /* ****************************
         * - Write Updated data
         * ****************************/

        $this->update_companies = $this->build_updated_company_array($this->companies_current, $this->companies_stored);

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
        $inner_html = array();
        $count = 0;

        $table_elements = $dom->getElementsByTagName('tr');

        foreach ($table_elements as $child)
        {
            if ($count > 0) {

                $tmp = array();

                $html = $child->ownerDocument->saveHTML($child);

                $company = substr($html, strpos($html, 'title=') +7);
                $company = substr($company, 0, strpos($company, 'href'));
                $company = trim($company);
                $company = rtrim($company, '"');

                if ($company !== '')
                {
                    $tmp['company'] = $company;
                    $tmp['date']    = date('Y-m-d H:i:s');
                    $tmp['count']   = 0;

                    $inner_html[] = $tmp;
                }
            }
            ++$count;
        }

        return $inner_html;
    }

    /**
     * Loops through each $companies array element and unsets elements that are older than a week.
     *
     * @param array $companies
     */
    protected function purge_old_companies(array &$companies)
    {
        $count = count($companies);
        $week_ago = time() - (86400 * 7);
        for ($c = 0 ; $c < $count ; ++$c)
        {
            $date = strtotime($companies[$c]['date']);
            if ($date < $week_ago)
            {
                unset($companies[$c]);
            }
        }

        // Reindex array
        $companies = array_values($companies);
    }

    /**
     * Looks for the jason.txt file. If the file is found, reads the file and returns an array from the
     * json_decoded content of the file. If the file doesn't exist, creates it by writing the json_encoded
     * string from $companies.
     *
     * @param array $companies_current
     * @return array If json.txt exists, return the json_decoded array. Else return the $companies array.
     */
    protected function get_companies_stored(array $companies_current)
    {
        $check_file = file_exists('json.txt');
        
        if (!$check_file)
        {
            $file = @fopen("json.txt","x");
            
            $json_data = json_encode($companies_current, true);
            echo fwrite($file, $json_data);
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
     * @return array
     */
    protected function build_updated_company_array(array $current_company, array $stored_company)
    {

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
                $out_company[$key]['date'] = date('Y-m-d H:i:s');
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
                $tmp['name'] = $current['name'];
                $tmp['date'] = date('Y-m-d H:i:s');
                $tmp['count'] = 0;

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
        echo fwrite($file, $json_date);
        fclose($file);
    }
}

$worker = new company_data_controller();
