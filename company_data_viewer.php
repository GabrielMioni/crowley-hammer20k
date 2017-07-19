<?php

class company_data_viewer
{
    protected $company_data;

    protected $html;

    public function __construct()
    {
        $this->company_data = $this->get_company_array();

        $this->html = $this->build_html($this->company_data);

    }

    protected function get_company_array()
    {
        $file = fopen('json.txt', 'r');

        $read = fread($file, filesize('json.txt'));
        fclose($file);

        $json_array = json_decode($read, true);

        return $json_array;
    }

    protected function build_html(array $company_data)
    {
        $table  = '<table>';
        $table .= '<thead>';
        $table .= '<tr><th>Company</th><th>Number of Days Up</th><th>Last Date / Time Up</th></tr>';
        $table .= '</thead>';
        $table .= '<tbody>';

        foreach ($company_data as $company_datum)
        {
            $company = $company_datum['company'];
            $count   = $company_datum['count'];
            $date    = $company_datum['date'];

            $table .= "<tr><td class='company'>$company</td><td>$count</td><td>$date</td></tr>";
        }

        $table .= '</tbody></table>';

        return $table;
    }

    public function return_table()
    {
        return $this->html;
    }
}
