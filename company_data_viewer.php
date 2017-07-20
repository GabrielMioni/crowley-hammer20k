<?php

/**
 * @package     Crowley-Hammer20k
 * @author      Gabriel Mioni <gabriel@gabrielmioni.com>
 */

/**
 * This class is responsible for returning an HTML table based on the JSON data object from json.txt.  It's used
 * to display the table at index.php
 */
class company_data_viewer
{
    /** @var array Holds the array created from the decoded JSON data on json.txt */
    protected $company_data;

    /** @var string Holds HTML for the table that displays company data. */
    protected $html_table;

    public function __construct()
    {
        // Read JSON data at json.txt
        $this->company_data = $this->get_company_array();

        // Sort the Company data - Companies that have the highest 'count' are on top
        $this->sort_on_day_count($this->company_data);

        // Build the HTML table
        $this->html_table = $this->build_html($this->company_data);
    }

    /**
     * Reads the json.txt file and decodes json object into a PHP array
     *
     * @return array Decoded array from the json.txt file
     */
    protected function get_company_array()
    {
        if (!file_exists('json.txt'))
        {
            return array();
        }

        $file = fopen('json.txt', 'r');

        $read = fread($file, filesize('json.txt'));
        fclose($file);

        $json_array = json_decode($read, true);

        return $json_array;
    }

    /**
     * Orders $company_data by records by 'count' value.
     *
     * @param array $company_data
     */
    protected function sort_on_day_count(array &$company_data)
    {
        usort( $company_data, function ($a, $b) { return $a['count'] < $b['count']; });
    }

    /**
     * Builds an HTML table to display the records from the JSON object.
     *
     * @param array $company_data
     * @return string
     */
    protected function build_html(array $company_data)
    {
        $table  = '<table>';
        $table .= '<thead id="thead">';
        $table .= '<tr><th>Row</th></th><th>Company</th><th>Price</th><th>Number of Days Up</th><th>Last Date / Time Up</th></tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        $row_num = 1;

        foreach ($company_data as $company_datum)
        {
            $company = str_replace('&amp;', '&', $company_datum['company']);
            $count   = $company_datum['count'];
            $date    = $company_datum['date'];
            $price   = isset($company_datum['price']) ? $company_datum['price'] : '';

            $table .= "<tr><td>$row_num</td><td class='company'>$company</td><td>$price</td><td>$count</td><td>$date</td></tr>";

            ++$row_num;
        }

        $table .= '</tbody></table>';

        return $table;
    }

    /**
     * @return string HTML for the table
     */
    public function return_table()
    {
        return $this->html_table;
    }
}
