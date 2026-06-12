<?php

namespace App\Http\Controllers\Admin\Settings;

use App\Http\Controllers\Controller;

class SettingsController extends Controller
{
    //
    public function backUpDB()
    {
        // ini_set('memory_limit', '4096M');
        // ini_set('max_execution_time', '0');
        // $dbhost = $_SERVER['SERVER_NAME'];
        // // dd($dbhost);
        // $dbuser = 'root';
        // $dbpass = '';
        // $dbname = 'rolworks_pos';
        // $connection = mysqli_connect($dbhost, $dbuser, $dbpass, $dbname);
        // $backupAlert = '';
        // $tables = array();
        // $result = mysqli_query($connection, "SHOW TABLES");
        // if (!$result) {
        //     $backupAlert = 'Error found.<br/>ERROR : ' . mysqli_error($connection) . 'ERROR NO :' . mysqli_errno($connection);
        // } else {
        //     while ($row = mysqli_fetch_row($result)) {
        //         $tables[] = $row[0];
        //     }
        //     mysqli_free_result($result);

        //     $return = '';
        //     foreach ($tables as $table) {

        //         $result = mysqli_query($connection, "SELECT * FROM " . $table);
        //         if (!$result) {
        //             $backupAlert = 'Error found.<br/>ERROR : ' . mysqli_error($connection) . 'ERROR NO :' . mysqli_errno($connection);
        //         } else {
        //             $num_fields = mysqli_num_fields($result);
        //             if (!$num_fields) {
        //                 $backupAlert = 'Error found.<br/>ERROR : ' . mysqli_error($connection) . 'ERROR NO :' . mysqli_errno($connection);
        //             } else {
        //                 $return .= 'DROP TABLE ' . $table . ';';
        //                 $row2 = mysqli_fetch_row(mysqli_query($connection, 'SHOW CREATE TABLE ' . $table));
        //                 if (!$row2) {
        //                     $backupAlert = 'Error found.<br/>ERROR : ' . mysqli_error($connection) . 'ERROR NO :' . mysqli_errno($connection);
        //                 } else {
        //                     $return .= "\n\n" . $row2[1] . ";\n\n";
        //                     for ($i = 0; $i < $num_fields; $i++) {
        //                         while ($row = mysqli_fetch_row($result)) {
        //                             $return .= 'INSERT INTO ' . $table . ' VALUES(';
        //                             for ($j = 0; $j < $num_fields; $j++) {
        //                                 $row[$j] = addslashes($row[$j]);
        //                                 if (isset($row[$j])) {
        //                                     $return .= '"' . $row[$j] . '"';
        //                                 } else {
        //                                     $return .= '""';
        //                                 }
        //                                 if ($j < $num_fields - 1) {
        //                                     $return .= ',';
        //                                 }
        //                             }
        //                             $return .= ");\n";
        //                         }
        //                     }
        //                     $return .= "\n\n\n";
        //                 }

        //                 $backup_file = $dbname . date("Y-m-d-H-i-s") . '.sql';
        //                 $handle = fopen("D:/Rolworks/BackupDB/".$backup_file."", 'w+');
        //                 // $handle = fopen("C:/Users/Teresita Leosala/Desktop/Backups/{$backup_file}", 'w+');
        //                 fwrite($handle, $return);
        //                 fclose($handle);
        //                 $backupAlert = 'Succesfully got the backup!';
        //             }
        //         }
        //     }
        // }
        /*
        * This script only works on linux.
        * It keeps only 31 backups of past 31 days, and backups of each 1st day of past months.
        */
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', '0');
        $host = 'localhost';
        $username = 'root';
        $password = '';
        $database_name = 'rolworks_pos';

        // Get connection object and set the charset
        $conn = mysqli_connect($host, $username, $password, $database_name);
        $conn->set_charset('utf8');

        // Get All Table Names From the Database
        $tables = [];
        $sql = 'SHOW TABLES';
        $result = mysqli_query($conn, $sql);

        while ($row = mysqli_fetch_row($result)) {
            $tables[] = $row[0];
        }

        $sqlScript = '';
        foreach ($tables as $table) {

            // Prepare SQLscript for creating table structure
            $query = "SHOW CREATE TABLE $table";
            $result = mysqli_query($conn, $query);
            $row = mysqli_fetch_row($result);

            $sqlScript .= "\n\n".$row[1].";\n\n";

            $query = "SELECT * FROM $table";
            $result = mysqli_query($conn, $query);

            $columnCount = mysqli_num_fields($result);

            // Prepare SQLscript for dumping data for each table
            for ($i = 0; $i < $columnCount; $i++) {
                while ($row = mysqli_fetch_row($result)) {
                    $sqlScript .= "INSERT INTO $table VALUES(";
                    for ($j = 0; $j < $columnCount; $j++) {
                        $row[$j] = $row[$j];

                        if (isset($row[$j])) {
                            $sqlScript .= '"'.$row[$j].'"';
                        } else {
                            $sqlScript .= '""';
                        }
                        if ($j < ($columnCount - 1)) {
                            $sqlScript .= ',';
                        }
                    }
                    $sqlScript .= ");\n";
                }
            }

            $sqlScript .= "\n";
        }

        if (! empty($sqlScript)) {
            // Save the SQL script to a backup file
            $backup_file_name = $database_name.'_backup_'.time().'.sql';
            $fileHandler = fopen('D:/Rolworks/BackupDB/'.$backup_file_name.'', 'w+');
            $number_of_lines = fwrite($fileHandler, $sqlScript);
            fclose($fileHandler);

            // Download the SQL backup file to the browser
            // header('Content-Description: File Transfer');
            // header('Content-Type: application/octet-stream');
            // header('Content-Disposition: attachment; filename=' . basename($backup_file_name));
            // header('Content-Transfer-Encoding: binary');
            // header('Expires: 0');
            // header('Cache-Control: must-revalidate');
            // header('Pragma: public');
            // header('Content-Length: ' . filesize($backup_file_name));
            // ob_clean();
            // flush();
            // readfile($backup_file_name);
            // exec('rm ' . $backup_file_name);
            return redirect('/home')->with('msg', 'Backup successfully created!');
        }

    }
}
