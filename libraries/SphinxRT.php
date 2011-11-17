<?php

// SphinxRT Search Interface for CodeIgniter
class SphinxRT {
	// variables
	public $sphinxql_link;
	public $link_status = false;
	public $errors = array('1' => 'Err#1: Bad link',
						   '2' => 'Err#2: Missing structure',
						   '3' => 'Err#3: No results');
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
		if(!$this->sphinxql_link)
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
		if(!$this->check_link_status())
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
		// is the link working?
		if(!$this->check_link_status())
		{
			// link is already bad
			return array('error' => $this->errors[1]);
			
			// end
			break;
		}
		
		// do we have the right kind of information?
		if(isset($data_array['search']/*, $data_array['columns']*/))
		{
			// build first part of query
			$query = 'SELECT * FROM `' . $index_name . '` WHERE MATCH (\'' . $this->_escape($data_array['search']) . '\')';
			
			// execute query
			$result = $this->sphinxql_link->query($query);
			
			// clear result just incase a query has
			// already been run
			unset($this->storage['results']);
			
			// successful query?
			if($result)
			{
				// loop through results
				while($rows = $result->fetch_array())
				{
					// add in row
					$this->storage['results']['records'][] = $rows;
				}
				
				// we need meta information
				$result_meta = $this->sphinxql_link->query('SHOW META');
				
				// let's parse that result meta information
				while($rows_meta = $result_meta->fetch_array())
				{
					// add in meta
					$this->storage['results']['meta'][$rows_meta['Variable_name']] = $rows_meta['Value'];
				}
				
				// pass back all the result data
				return $this->storage['results'];
			} else {
				// no results
				return array('error' 	=> $this->errors[3],
							 'native' 	=> $this->sphinxql_link->error);
			}
		} else {
			// missing information
			return array('error' => $this->errors[2]);
		}
	}
	
	
}