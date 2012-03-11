<?php

// SphinxRT Search Interface for CodeIgniter
class SphinxRT
{
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
		}
		else 
		{
			// we did get an object
			$this->link_status = true;
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
	public function insert($index_name, $data_array, $id)
	{
		// is the link working?
		if(!$this->check_link_status())
		{
			// link is already bad
			return array('error' => $this->errors[1]);
			
			// end
			break;
		}
		
		// add in id
		$data_array['id'] = $id;
		
		// continue processing
		// process the fieldnames
		foreach($data_array as $key=>$value)
		{
			// build up column names
			$this->data['insert']['column_names'][] = '`' . $key . '`';
			
			// build up match data
			// add escaping
			$this->data['insert']['column_data'][] = '\'' . $this->_escape($value) . '\'';
		}
		
		// build query
		$query = 'INSERT INTO `' . $index_name . '`
						(' . implode(', ', $this->data['insert']['column_names']) . ')
					VALUES
						(' . implode(', ', $this->data['insert']['column_data']) . ')';
		
		// let's perform the query
		$result = $this->sphinxql_link->query($query) or die(mysqli_error($this->sphinxql_link));

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
		  'where' => array('id,=' => int,
		  				   'author_id,=' => int), (example columns)
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
		
		// clear up
		$this->_clear();
		
		// do we have the right kind of information?
		if(isset($data_array['search']/*, $data_array['columns']*/))
		{
			// build first part of query
			$query = 'SELECT * FROM `' . $index_name . '` WHERE MATCH (\'' . $this->_escape($data_array['search']) . '\')';
			
			// let's add in some more clauses
			if(isset($data_array['where']))
			{
				// we're looking to add some more
				// build up some cases
				foreach($data_array['where'] as $key=>$value)
				{
					// add into new array
					// explode values to find operators
					$new_operator = explode(',', $key);
					
					// escape
					$this->storage['temp']['search_where_clauses'][] = '`' . $new_operator[0] . '` ' . $new_operator[1] . ' \'' . $this->_escape($value) . '\'';
				}
				
				// implde them onto the query
				$query .= ' AND ' . implode(' AND ', $this->storage['temp']['search_where_clauses']);
			}
			
			// add start/limits?
			if(is_int($data_array['limit']) && is_int($data_array['start']))
			{
				// have some values, push these
				$query .= ' LIMIT ' . $data_array['start'] . ', ' . $data_array['limit'];
			}
			
			// execute query
			$result = $this->sphinxql_link->query($query);
			
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
			} 
			else 
			{
				// no results
				return array('error' 	=> $this->errors[3],
							 'native' 	=> $this->sphinxql_link->error);
			}
		} 
		else 
		{
			// missing information
			return array('error' => $this->errors[2]);
		}
	}
	
	// replace into, basically, update a record
	/**********************
	required array system
		just an array e.g.
		array('column_name' => 'column_data',
			  etc...);
	)
	**********************/
	public function update($index_name, $data_array)
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
			$this->data['update']['column_names'][] = '`' . $key . '`';
			
			// build up match data
			$this->data['update']['column_data'][] = '\'' . $this->_escape($value) . '\'';
		}
		
		// build query
		$query = 'REPLACE INTO `' . $index_name . '`
						(' . implode(', ', $this->data['update']['column_names']) . ')
					VALUES
						(' . implode(', ', $this->data['update']['column_data']) . ')';
		
		// let's perform the query
		$result = $this->sphinxql_link->query($query);
		
		// reset insert data
		unset($this->data['update'], $query);
		
		// did it work?
		return $result !== false;
	}
	
	// truncate an index
	public function truncate($index_name)
	{
		// is the link working?
		if(!$this->check_link_status())
		{
			// link is already bad
			return array('error' => $this->errors[1]);
			
			// end
			break;
		}
		
		// build query
		$query = 'TRUNCATE RTINDEX `' . $index_name . '`';
		
		// perform query
		$result = $this->sphinxql_link->query($query);
		
		// reset truncate data
		unset($index_name);
		
		// did it work?
		return $result !== false;
	}
	
	// delete an item
	public function delete($index_name, $data_array)
	{
		// is the link working?
		if(!$this->check_link_status())
		{
			// link is already bad
			return array('error' => $this->errors[1]);
			
			// end
			break;
		}
		
		// build query
		$query = 'DELETE FROM `' . $index_name . '`';
		
		// is it a query?
		if(is_array($data_array))
		{
			// process
			
		}
		else
		{
			// give it a raw query
			$query .= ' WHERE ' . $data_array;
		}
		
		// perform query
		$result = $this->sphinxql_link->query($query);
		
		// reset data
		unset($index_name, $data_array);
		
		// did it work?
		return $result !== false;
	}

	// clear storage items that might get in the way
	public function _clear()
	{
		// clear
		unset($this->storage['results'],
			  $this->storage['temp']);
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
		} 
		else 
		{
			// failed
			return false;
		}
	}
}