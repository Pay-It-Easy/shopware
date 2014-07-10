<?php
if(!class_exists('simpleLogger')){
	class simpleLogger{
		private $LOGFILENAME;
		private $level;
		private $canLog;

		function simpleLogger($logfilename='',$loglevel='DEBUG'){
			$this->level=simpleLoggerLevels::fromString($loglevel);
			$this->canLog=false;
			if($logfilename==''){
				$this->LOGFILENAME= '/tmp/PayItEasy_transaction.log';
			}
			else{
				$this->LOGFILENAME=$logfilename;
			}


			try{
				if($this->level!=simpleLoggerLevels::NONE){
					if(file_exists($this->LOGFILENAME) && is_writable ( $this->LOGFILENAME)){
						$this->canLog=true;
					}
					else if(!file_exists($this->LOGFILENAME))
					{
						$fh = fopen($this->LOGFILENAME, 'a') ;
						fclose($fh);
						$this->canLog=true;
					}
					else {
						$this->LOGFILENAME='/tmp/simpleLogger.log';
						$fh = fopen($this->LOGFILENAME, 'a') ;
						fclose($fh);
						$this->canLog=true;
						$this->LOGFILENAME=simpleLoggerLevels::INFO;
					}
				}
			}catch (Exception $e){
			}
		}

		/**
		 * logToFile([$logLevel,]$message[, $logfile])
		 *
		 * Author(s): younes
		 * Date: May 11, 2013
		 *
		 * Writes the values of certain variables along with a message in a log file.
		 *
		 * Parameters:
		 *  $logLevel:  logLevel
		 *  $message:   Message to be logged
		 *
		 * Returns array:
		 *  $result[status]:   True on success, false on failure
		 *  $result[message]:  Error message
		 */

		function logToFile( $logLevel=simpleLoggerLevels::DEBUG, $message) {

			if(!$this->canLog)
				return;

			if($logLevel < $this->level)
				return;

			// Get time of request
			if( ($time = $_SERVER['REQUEST_TIME']) == '') {
				$time = time();
			}

			// Get IP address
			if( ($remote_addr = $_SERVER['REMOTE_ADDR']) == '') {
				$remote_addr = "REMOTE_ADDR_UNKNOWN";
			}

			// Get requested script
			if( ($request_uri = $_SERVER['REQUEST_URI']) == '') {
				$request_uri = "REQUEST_URI_UNKNOWN";
			}

			// Format the date and time
			$date = date("Y-m-d H:i:s", $time);

			try{

			// Append to the log file
			if($fd = @fopen($this->LOGFILENAME, "a")) {
				//$result = fputcsv($fd, array($date, $remote_addr, $request_uri,$, $message));
				$result = fputcsv($fd, array($date, $remote_addr ,simpleLoggerLevels::toString($logLevel), $message));
				fclose($fd);

				if($result > 0)
					return '';
				else
					return  'Unable to write to '.$this->LOGFILENAME.'!';
			}
			else {
				return 'Unable to open log '.$this->LOGFILENAME.'!';
			}
			}
			catch (Exception $e){

			}
		}

		function info($value = '') {
			self::logToFile(simpleLoggerLevels::INFO, $value);
		}


		function warning($value = '') {
			self::logToFile(simpleLoggerLevels::WARN, $value);
		}

		function error($value = '') {
			self::logToFile(simpleLoggerLevels::ERROR, $value);
		}
		function debug($value = '') {
			self::logToFile(simpleLoggerLevels::DEBUG, $value);
		}
	}

	class simpleLoggerLevels {
		const TRACE=0;
		const DEBUG=10;
		const INFO=20;
		const WARN=30;
		const ERROR=40;
		const NONE=100;


		static function toString($level){
			$str='';
			switch ($level){
				case simpleLoggerLevels::TRACE:
					$str='TRACE';
					break;
				case simpleLoggerLevels::DEBUG:
					$str='DEBUG';
					break;
				case simpleLoggerLevels::INFO:
					$str='INFO';
					break;
				case simpleLoggerLevels::WARN:
					$str='WARN';
					break;
				case simpleLoggerLevels::ERROR:
					$str='ERROR';
					break;
				default:
					break;
			}
			return $str;
		}

		static function fromString($level){
			$loglevel=simpleLoggerLevels::NONE;

			switch ($level){
				case 'TRACE':
					$loglevel= simpleLoggerLevels::TRACE;
					break;
				case 'DEBUG':
					$loglevel= simpleLoggerLevels::DEBUG;
					break;
				case 'INFO':
					$loglevel= simpleLoggerLevels::INFO;
					break;
				case 'WARN':
					$loglevel= simpleLoggerLevels::WARN;
					break;
				case 'ERROR':
					$loglevel= simpleLoggerLevels::ERROR;
					break;
				default:
					$loglevel= simpleLoggerLevels::NONE;
					break;
			}
			return $loglevel;
		}
	}
}
?>