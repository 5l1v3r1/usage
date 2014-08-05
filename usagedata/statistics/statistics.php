<?php

defined('USAGEDATA_INCLUDED') or die();

class UsagedataStatistics
{
    /** @var array Associative array with methods and recurring time (days)*/
	protected $timedTasks = array();

    protected $dataStorage = null;

    protected $metaStorage = null;

    protected $uploadFrequency = 10;

	public function __construct($config = array())
	{
        if(isset($config['dataPath']))
        {
            $this->dataStorage = new UsagedataStorage($config['dataPath']);
        }

        if(isset($config['metaPath']))
        {
            $this->metaStorage = new UsagedataStorage($config['metaPath']);
        }

		$this->setupTimedTasks();
        $this->restoreData();
	}

	/**
	 * Performs final tasks (save data, checks if has to run time-based task, uploads data ecc ecc)
	 */
	public function closeSession()
	{
		$this->runTimedTasks();
		$this->saveData();
	}

	/**
	 * Push a property value into the save stack.
	 *
	 * @param string $property 	Property you're currently loggin (ie. JVERSION)
	 * @param string $value 	Property value (ie. 2.5.7)
	 */
	public final function addData($property, $value)
	{
        $data = $this->dataStorage->get('storage', array());
		$data[] = array('property' => $property, 'value' => $value, 'time' => time());

        $this->dataStorage->set('storage', $data);
	}

	/**
	 * Uploads usage data to your server
	 */
	public final function upload()
	{
        if (time() >= $this->metaStorage->get('nextUpload', 0))
        {
            return;
        }

		$config = UsageDataConfig::getInstance();
		$url    = $config->submitDomain;
		$secret = $config->secret;

		if(!$url) return;
		//if(!$url || $secret) return;

		// strip out prefix from the url
		$url = str_replace('http://', '', $url);
		$url = str_replace('https://', '', $url);

		$path = '/index.php?option=com_usage&view=remote&task=remotelog&format=json';

		$string = json_encode($this->prepareData());
		$return = json_decode($this->doHttpPost($url, $path, array('json' => $string)), true);

		if($return['result'] == 'true')
		{
			$this->cleanSavedData();
		}
	}

	protected function setupTimedTasks()
	{
	}

	protected function runTimedTasks()
	{
        $active = array();
        $tasks  = $this->metaStorage->get('tasks', array());

        foreach($tasks as $task => $time)
        {
            if(time() >= $time)
            {
                $active[] = $task;
            }
        }

		foreach ($active as $task)
		{
			if(method_exists($this, $task))
            {
				$this->setTimedTask($task);
                $this->$task();
			}
		}
	}

	/**
	 * Sets the method UsageData should call after X days
	 *
	 * @param string $updateTask Associative array of tasks and frequency
	 */
	protected function setTimedTask($updateTask = '')
	{
		$savedTasks = $this->metaStorage->get('tasks');
		$timedTasks = $this->timedTasks;

		foreach ($timedTasks as $task => $frequency)
		{
			// I'm updating only a set of tasks
			if($updateTask && !in_array($task, $updateTask))
            {
                continue;
            }

			$savedTasks[$task] = time() + ($frequency * 24 * 3600);
		}

        $this->metaStorage->set('tasks', $savedTasks);
		$this->metaStorage->save();
	}

	protected function restoreData()
	{
        $this->dataStorage->load();
	}

	/**
	 * Stores savedData into the filesystem
	 */
	protected function saveData()
	{
        $this->dataStorage->save();
	}

	protected function setNextUpload()
	{
        $nextUpload = time() + ($this->uploadFrequency * 24 * 3600);

        $this->metaStorage->set('nextUpload', $nextUpload);
		$this->metaStorage->save();
	}

    /**
	 * Wipes out saved data successfully uploaded
	 */
	private function cleanSavedData()
	{
		/*$this->savedData = array();
		$file = str_replace('classes', 'store', dirname(__FILE__)).'/usageData.txt';
		@unlink($file);*/
	}

	private function prepareData()
	{
		$post['id'] 	  = $this->getServerId();
		$post['password'] = md5($secret.date('Y-m-d H'));
		$post['local'] 	  = $this->isLocal();

		// no save data, let's look if i have anything saved on filesystem
		if(!$this->savedData)
        {
			$this->restoreData();
		}

		foreach ($this->savedData as $event => $element)
		{
			$log['event'] = $event;

			foreach($element as $property)
			{
				$log['data'][] = array('property' => $property['property'],
									   'value'	  => $property['value'],
									   'time'	  => $property['time']);
			}

			$logs[] = $log;
		}

		$post['logs'] = $logs;

		return $post;
	}

	/**
	 * Gets the server unique ID, or creates it if it's not present
	 * @return   string   Server id
	 */
	private function getServerId()
	{
		$id = $this->metaStorage->get('serverId', '');

		// creates an unique id using the microtime function (there are no servers with the same microtime)
		if(!$id)
		{
			$id = md5(microtime());
			$this->metaStorage->set('serverId', $id);
			$this->metaStorage->save();
		}

		return $id;
	}

	/**
	 * Is this server on a local installation or on a live site?
	 * @return bool Is local?
	 */
	private function isLocal()
	{
		return (int)(strpos($_SERVER['SERVER_NAME'], 'localhost') !== false);
	}

	protected function doHttpPost($hostname, $path, $fields)
	{
		$result = "";
		// URLencode the post string
		$fields_string = "";

		foreach($fields as $key => $value)
		{
			if (is_array($value))
			{
				foreach ($value as $v)
				{
					$fields_string .= $key . '[]=' . $v . '&';
				}
			}
			else
			{
				$fields_string .= $key.'='.$value.'&';
			}
		}
		rtrim($fields_string,'&');

		// cURL or something else
		if (function_exists('curl_init'))
		{
			$curlsession = curl_init();
			curl_setopt($curlsession, CURLOPT_URL, "http://" . $hostname . $path);
			curl_setopt($curlsession, CURLOPT_POST, count($fields));
			curl_setopt($curlsession, CURLOPT_POSTFIELDS, $fields_string);
			curl_setopt($curlsession, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curlsession, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($curlsession, CURLOPT_SSL_VERIFYPEER, false);
			$result = curl_exec($curlsession);
		}
		else
		{
			// Build a header
			$http_request  = "POST $path HTTP/1.1\r\n";
			$http_request .= "Host: $hostname\r\n";
			$http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
			$http_request .= "Content-Length: " . strlen($fields_string) . "\r\n";
			$http_request .= "Connection: Close\r\n";
			$http_request .= "\r\n";
			$http_request .= $fields_string ."\r\n";

			$result = '';
			$errno = $errstr = "";
			$fs = fsockopen("ssl://" . $hostname, 443, $errno, $errstr, 10);
			if( false == $fs )
			{
				//die('Could not open socket');
			}
			else
			{
				fwrite($fs, $http_request);
				while (!feof($fs))
				{
					$result .= fgets($fs, 4096);
				}

				$result = explode("\r\n\r\n", $result, 2);
				$result = $result[1];
			}
		}

		return $result;
	}

	public static function &getInstance()
	{
		static $instance = null;

		if(!is_object($instance))
        {
			$instance = new self;
		}

		return $instance;
	}
}