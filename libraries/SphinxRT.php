<?php

// SphinxRT Search Interface for CodeIgniter
class SphinxRT {
	// variables
	public $sphinxql_link;
	public $link_status = false;
	public $errors = array('1' => 'Err#1: Bad link',
						   '2' => 'Err#2: Missing structure');
	public $storage = array();
	public $counter = 1;
	private $CI;
	
	// construct
	public function __construct()
	{
		// get CI
		$this->CI = &get_instance();
		
		// load the config
		$this->CI->config->load('sphinxrt');

		// attempt to connect to Sphinx
		$this->sphinxql_link = new mysqli($this->CI->config->config['hostname'] . ':' . $this->CI->config->config['port']);
		
		// did the link work?
		if($this->sphinxql_link === false)
		{
			// update link status
			$this->link_status = false;
			
			// didn't work
			throw new Exception('Unable to communicate to the Sphinx Server');
		} else {
			// we did get an object
			$this->link_status = true;
		}
	}
	
	// escape a string to Sphinx standard
	public function _escape($string)
	{
		// remove tags, if any
		$string = strip_tags($string);
		
		// trim
		$string = trim($string);
		
		// scape the main things
		$from = array('\\', '(',')','|','-','!','@','~','"','&', '/', '^', '$', '=', ';', '\'');
		$to   = array('\\\\', '\(','\)','\|','\-','\!','\@','\~','\"', '\&', '\/', '\^', '\$', '\=', '\;', '\\\'');
		
		// execute
		$string = str_replace($from, $to, $string);
		
		// remove new lines, they aren't needed
		$string = str_replace(array("\r", "\r\n", "\n"), ' ', $string);
		
		// remove whitespace
		$string = preg_replace('/(?:(?)|(?))(\s+)(?=\<\/?)/', ' ', $string);
		
		// return
		return $string;
	}
	
	// check link status
	public function check_link_status()
	{
		// is the link available?
		if($this->link_status)
		{
			// is there
			return true;
		} else {
			// failed
			return false;
		}
	}
	
	// insert record
	/**********************
	required array system
		just an array e.g.
		array('column_name' => 'column_data',
			  etc...);
	)
	**********************/
	public function insert($index_name, $data_array)
	{
		// is the link working?
		if(!$this->check_link_status)
		{
			// link is already bad
			return array('error' => $this->errors[1]);
			
			// end
			break;
		}
		
		// continue processing
		// process the fieldnames
		foreach($data_array as $key=>$value)
		{
			// build up column names
			$this->data['insert']['column_names'][] = '`' . $key . '`';
			
			// build up match data
			$this->data['insert']['column_data'][] = '\'' . $this->_escape($value) . '\'';
		}
		
		// build query
		$query = 'INSERT INTO `' . $index_name . '`
						(' . implode(', ', $this->data['insert']['column_names']) . ')
					VALUES
						(' . implode(', ', $this->data['insert']['column_data']) . ')';
		
		// let's perform the query
		$result = $this->sphinxql_link->query($query);

		// reset insert data
		unset($this->data['insert'], $query);
		
		// did it work?
		return $result !== false; # these lines were stolen by Phil Sturgeon
	}
	
	// perform a search
	/**********************
	required array system
	array('search' => 'query',
		  'limit' => 'int',
		  'start' => 'int',
		  'columns' => array([] => 'column_name'); # this will be added later
		  										   # to allow for more complex
												   # queries to take place
	)
	
	**********************/
	public function search($index_name, $data_array)
	{
		
		// do we have the right kind of information?
		if(isset($data_array['search'], $data_array['columns']))
		{
			// build first part of query
			$query = 'SELECT * FROM `' . $index_name . '` ';
			/*
			// add in where clause
			$query .= ' WHERE MATCH (';
			
			// match
			foreach($data_array['columns'] as $key=>$value)
			{
				// where is the counter?
				if($this->counter > 1)
				{
					// add comma
					$query .= ', ';
				}
				
				// add column name
				$query .= '`' . $value . '`';
				
				// increment counter
				$this->counter++;
			}
			
			// reset counter
			$this->counter = 1;*/
			// add in where clause + basic search query
			$query .= 'WHERE MATCH (' . mysql_real_escape_string($data_array['search'], $this->sphinxql_link) . ');';
			
			// execute query
			$result = mysql_query($query, $this->sphinxql_link);
			
			// clear result just incase a query has
			// already been run
			unset($this->storage['results']);
			
			// loop through results
			while($rows = mysql_fetch_assoc($result, $this->sphinxql_link))
			{
				// add in row
				$this->storage['results']['records'][] = $rows;
			}
			
			// we need meta information
			$result_meta = mysql_query('SHOW META;', $this->sphinxql_link);
			
			// let's parse that result
			while($rows_meta = mysql_fetch_assoc($result_meta, $this->sphinxql_link))
			{
				print_r($result_meta);
			}
		} else {
			// missing information
			return array('error' => $this->errors[2]);
		}
	}
	
	
}