<?php

class GetChunks
{
    protected $input_file;
    protected $output_file;
    protected $num_chunks;
    protected $chunk_size;
    protected $total_size_requested;
    protected $input_file_size;
    protected $curl_handles = [];
    protected $multi_handle;
    protected $response_data;
    protected $interval = 1;
    protected $timestamp;

    public function __construct($options)
    {
        $this->input_file = $options['f'];
        $this->output_file = $options['o'];
        $this->num_chunks = (int) $options['c'];
        $this->chunk_size = (int) $options['s'];
        $this->timestamp = time();

        $this->total_size_requested = $this->num_chunks * $this->chunk_size;
    }

    public function confirmOutputFile()
    {
        if (is_writable(dirname($this->output_file))) {
            return true;
        } else {
            echo "\nOutput file provided is not writable [$this->output_file]\n";
            return false;
        }
    }

    public function downloadFile()
    {
        try {
            $this->checkDownloadParams();
            $this->prepDownload();
            $this->beginDownloads();
            $this->closeConnections();
            $this->assembleData();
            $this->writeOutputFile();
            $this->displayReport();
        } catch (Exception $e) {
            echo $e->getMessage()."\n";
        }
    }

    protected function checkDownloadParams()
    {
        if ($this->total_size_requested <= 0) {
            throw new Exception('Please provide values greater than zero (0) for -c (number of chunks) and -s (chunk size).');
        }

        echo "\nInput File\t\t$this->input_file";
        echo "\nOutput File\t\t$this->output_file";
        echo "\nChunk Size\t\t$this->chunk_size";
        echo "\nNumber of Chunks\t$this->num_chunks";
        echo "\nTotal Size Requested\t$this->total_size_requested";
    }

    protected function prepDownload()
    {

        //- Init the curl handles
        for ($i = 0; $i < $this->num_chunks; ++$i) {
            $this->curl_handles[$i] = curl_init();
        }

        //- This is for setting up parallel curl calls to improve download efficiency
        $this->multi_handle = curl_multi_init();

        //- Setup each curl handle and add it to the multi curl
        foreach ($this->curl_handles as $index => $handle) {
            $begin = $index * $this->chunk_size;
            $end = $begin + ($this->chunk_size - 1);
            $this->prepCurlHandle($handle, $begin, $end);
            curl_multi_add_handle($this->multi_handle, $handle);
        }
    }

    protected function prepCurlHandle(&$ch, $begin, $end)
    {
        curl_setopt($ch, CURLOPT_URL, $this->input_file);
        curl_setopt($ch, CURLOPT_RANGE, $begin.'-'.$end);
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOPROGRESS, false);
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array($this, 'downloadProgress'));
    }

    protected function beginDownloads()
    {
        try {
            echo "\n\nBeginning Download .";
            $active = null;
            do {
                curl_multi_exec($this->multi_handle, $active);
            } while ($active);
        } catch (Exception $e) {
            echo "\nThere was an error reading the file data: \n";
            throw $e;
        }
    }

    protected function closeConnections()
    {
        try {
            foreach ($this->curl_handles as $index => $handle) {
                curl_multi_remove_handle($this->multi_handle, $handle);
            }

            curl_multi_close($this->multi_handle);
        } catch (Exception $e) {
            echo "\nThere was an error reading the file data: \n";
            throw $e;
        }
    }

    protected function assembleData()
    {
        try {
            $responses = '';
            foreach ($this->curl_handles as $index => $handle) {
                $responses .= curl_multi_getcontent($handle);
            }

            $this->response_data = $responses;
        } catch (Exception $e) {
            echo "\nThere was an error reading the file data: \n";
            throw $e;
        }
    }

    protected function writeOutputFile()
    {
        try {
            //- Delete any existing file first
            @unlink($this->output_file);
            $file = fopen($this->output_file, 'w');
            fwrite($file, $this->response_data);
            fclose($file);
        } catch (Exception $e) {
            echo "\nThere was an error saving the output file [$this->output_file]: \n";
            throw $e;
        }
    }

    protected function displayReport()
    {
        $file_size = filesize($this->output_file);
        if ($file_size == $this->total_size_requested) {
            echo "\nThe file downloaded successfully! [$this->output_file][$file_size]\n";
        } elseif ($file_size) {
            echo "\nThe file downloaded, but the resulting filesize is incorrect! [$this->output_file] : $this->total_size_requested != $file_size\n";
        } else {
            echo "\nThe file failed to download!\n";
        }
    }

    public function downloadProgress($resource, $download_size, $downloaded, $upload_size, $uploaded)
    {
        $current_time = time();
        if ($current_time >= ($this->timestamp + $this->interval)) {
            echo ".";
            $this->timestamp = time();
        }
    }
}
