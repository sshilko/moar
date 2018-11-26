<?php
/**
 * This class refactors original SphinxAPI client
 * PHP version of Sphinx searchd client (PHP API)
 *
 * $client = \sshilko\SphinxClient::getInstance('foobar1')
 *
 * Copyright 2018 Sergei Shilko
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or substantial
 * portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */
namespace sshilko;

class SphinxClient extends \SphinxClient
{
    private $persist         = false;

    private $socket_shutdown = true;

    private $custom_chunksize   = false;

    private $connect_savesocket = true;


    private const READ_WRITE_TIMEOUT = 10;

    private const STREAM_CHUNK_SIZE  = 8192;

    private static $_instance = null;

    protected function __construct()
    {
        parent::__construct();
    }

    protected function __clone()
    {
    }

    public static function getInstance(string $id) : self
    {
        if (isset(self::$_instance[$id])) {
            return self::$_instance[$id];
        }
        self::$_instance[$id] = new self();

        return self::$_instance[$id];
    }

    function __destruct()
    {
        if (!$this->persist) {
            if (is_resource($this->_socket)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR);
                }
                fclose($this->_socket);
            }
            $this->_socket = false;
        }
    }

    function _Send($handle, $data, $len)
    {
        // get status of socket to determine whether or not it has timed out
        $info = @stream_get_meta_data($handle);

        if (($info['eof'] && !$info['unread_bytes']) || @feof($handle)) {
            $this->_error = "Error sending data. Socket connection EOF";
            $this->_connerror = true;
            return false;
        }

        if ($info['timed_out']) {
            $this->_error = "Error sending data. Socket connection TIMED OUT";
            $this->_connerror = true;
            return false;

        }

        $tries = 2;
        for ($written = 0; $written < $len; true) {

            $fwrite = fwrite($handle, substr($data, $written));
            fflush($handle);
            $written += (int) ($fwrite);

            if ($fwrite === false || (feof($handle) && $written < $len)) {
                $this->_error = "Failed to fwrite() to socket: " . ($len - $written) . 'bytes left';
                $this->_connerror = true;
                return false;
            }

            if ($fwrite === 0) {
                $tries--;
            }

            if ($tries <= 0) {
                $this->_error = 'Failed to write to socket after some retries';
                $this->_connerror = true;
                return false;
            }
        }

        return true;
    }

    function Close()
    {
        if (!$this->persist) {
            if (is_resource($this->_socket)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR);
                }
                fclose($this->_socket);
            }
            $this->_socket = false;
        }
        return true;
    }

    function _Connect()
    {
        if ($this->_socket !== false) {
            if (is_resource($this->_socket)) {
                $info = [];
                if (!$this->_connerror) {
                    $info = stream_get_meta_data($this->_socket);
                }
                if ($this->_connerror || ($info['eof'] && !$info['unread_bytes']) || $info['timed_out'] || feof($this->_socket))  {
                    /**
                     * It was a resource, by it's dead now (no matter persistent or not)
                     */
                    if ($this->socket_shutdown) {
                        stream_socket_shutdown($this->_socket, STREAM_SHUT_RDWR);
                    }
                    fclose($this->_socket);
                    $this->_socket = false;
                } else {
                    /**
                     * We already have healthy socket, no need to connect
                     */
                    $this->_connerror = false;
                    return $this->_socket;
                }
            }
            /**
             * Whatever it was, its not a resource we needed
             */
            $this->_socket = false;
        }

        $errno  = 0;
        $errstr = "";
        $this->_connerror = false;

        if ($this->persist) {
            $remote = sprintf('tcp://%s:%s/%s', $this->_host, $this->_port, getmypid());
        } else {
            $remote = sprintf('tcp://%s:%s', $this->_host, $this->_port);
        }

        $fp = @stream_socket_client($remote,
                                    $errno,
                                    $errstr,
                                    /**
                                     * Open connection timeout ONLY
                                     */
                                    ($this->_timeout > 0) ? $this->_timeout : null,
                                    ($this->persist                                        ?
                                        (STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT) :
                                        (STREAM_CLIENT_CONNECT))
                                    );

        if (!$fp) {
            $errstr = trim($errstr);
            $this->_error = "connection to $remote failed (errno=$errno, msg=$errstr)";
            $this->_connerror = true;
            return false;
        }

        if (!stream_set_timeout($fp, self::READ_WRITE_TIMEOUT)) {
            throw new \Exception("Timeout (stream_set_timeout) could not be set");
        }

        $rbuff = stream_set_read_buffer($fp, 0);
        if (!(0 === $rbuff)) {
            throw new \Exception("Read buffer could not be set");
        }

        if (!stream_set_blocking($fp, 1)) {
            throw new \Exception("Blocking could not be set");
        }

        /**
         * this is not reliably returns success (0)
         */
        stream_set_write_buffer($fp, 0);

        if ($this->custom_chunksize) {
            stream_set_chunk_size($fp, self::STREAM_CHUNK_SIZE);
        }

        if (!$this->_Send($fp, pack("N", 1), 4)) {
            if ($this->socket_shutdown) {
                stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
            }
            fclose($fp);
            $this->_error = "failed to send client protocol version";
            return false;
        }

        $readVersion = null;
        try {
            $readVersion = $this->read($fp, 4);

            list(, $v) = unpack("N*", $readVersion);
            if (((int) $v) < 1) {
                $this->_error = "expected searchd protocol version 1+, got version '$v'";
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                }
                fclose($fp);
                return false;
            }
        } catch (\Exception $ex) {
            $this->_error = 'Error reading searchd protocol version at ' . __FUNCTION__ . ', got response size: ' . \strlen($readVersion);
            if ($this->socket_shutdown) {
                stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
            }
            fclose($fp);
            return false;
        }

        /**
         * If we use persistent connection, this SEARCHD_COMMAND_PERSIST should only be done
         * one-first time, but we cant know if our persistent connection is first one
         */
        // command, command version = 0, body length = 4, body = 1
        //$req = pack("nnNN", SEARCHD_COMMAND_PERSIST, 0, 4, 1);
        //if (!$this->_Send($fp, $req, 12)) {
        //    fclose($fp);
        //    return false;
        //}

        $this->_error     = false;
        $this->_connerror = false;
        if ($this->connect_savesocket) {
            $this->_socket = $fp;
        }
        return $fp;
    }

    function _GetResponse( $fp, $client_ver )
    {
        $info = stream_get_meta_data($fp);

        if (($info['eof'] && !$info['unread_bytes']) || @feof($fp))  {
            if (is_resource($fp)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                }
                fclose($fp);
            }
            $this->_error = 'Error reading data. Socket connection EOF';
            return false;
        }

        if ($info['timed_out']) {
            if (is_resource($fp)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                }
                fclose($fp);
            }
            $this->_error = 'Error reading data. Socket connection TIME OUT';
            return false;
        }

        $response = "";
        $len = 0;


        $status = '';
        try {
            $header = $this->read($fp, 8);
        } catch (\Exception $ex) {
            if (is_resource($fp)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                }
                fclose($fp);
            }
            $this->_error = $ex->getMessage();
            return false;
        }

        if (strlen($header) == 8)
        {
            list($status, $ver, $len) = array_values(unpack("n2a/Nb", $header));
            try {
                $response = $this->read($fp, $len);
            } catch (\Exception $ex) {
                if (is_resource($fp)) {
                    if ($this->socket_shutdown) {
                        stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                    }
                    fclose($fp);
                }
                $this->_error = $ex->getMessage();
            }
        }

        $read = strlen($response);
        if (!$response || $read != $len) {
            $this->_error = $len
                ? "failed to read searchd response (status=$status, ver=$ver, len=$len, read=$read)"
                : "received zero-sized searchd response";
            if (is_resource($fp)) {
                if ($this->socket_shutdown) {
                    stream_socket_shutdown($fp, STREAM_SHUT_RDWR);
                }
                fclose($fp);
            }
            return false;
        }

        // check status
        if ($status == SEARCHD_WARNING)
        {
            list(,$wlen) = unpack ( "N*", substr ( $response, 0, 4 ) );
            $this->_warning = substr ( $response, 4, $wlen );
            trigger_error($this->_warning, E_USER_WARNING);
            return substr ( $response, 4+$wlen );
        }
        if ($status == SEARCHD_ERROR)
        {
            $this->_error = "searchd error: " . substr ( $response, 4 );
            trigger_error($this->_error, E_USER_WARNING);
            return false;
        }
        if ($status == SEARCHD_RETRY)
        {
            $this->_error = "temporary searchd error: " . substr ( $response, 4 );
            trigger_error($this->_error, E_USER_WARNING);
            return false;
        }
        if ($status != SEARCHD_OK)
        {
            $this->_error = "unknown status code '$status'";
            trigger_error($this->_error, E_USER_WARNING);
            return false;
        }

        // check version
        if ($ver < $client_ver)
        {
            $this->_warning = sprintf ( "searchd command v.%d.%d older than client's v.%d.%d, some options might not work",
                $ver>>8, $ver&0xff, $client_ver>>8, $client_ver&0xff );
            trigger_error($this->_warning, E_USER_WARNING);
        }

        return $response;
    }

    private function read($sock, $n)
    {
        $info = stream_get_meta_data($sock);

        if (($info['eof'] && !$info['unread_bytes']) || @feof($sock))  {
            throw new \Exception('Error reading data. Socket connection EOF');
        }

        if ($info['timed_out']) {
            throw new \Exception('Error reading data. Socket connection TIME OUT');
        }

        $tries        = 2;
        $fread_result = '';
        while (!@feof($sock) && strlen($fread_result) < $n) {
            /**
             * Up to $n number of bytes read.
             */
            $fdata = @fread($sock, $n);
            if (false === $fdata) {
                throw new \Exception("Failed to fread() from socket");
            }
            $fread_result .= $fdata;

            if (!$fdata) {
                $tries--;
            }

            if ($tries <= 0) {
                /**
                 * Nothing to read
                 */
                break;
            }

        }
        return $fread_result;
    }
}