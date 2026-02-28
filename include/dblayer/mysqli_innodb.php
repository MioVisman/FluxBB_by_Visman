<?php

/**
 * Copyright (C) 2008-2012 FluxBB
 * based on code by Rickard Andersson copyright (C) 2002-2008 PunBB
 * License: http://www.gnu.org/licenses/gpl.html GPL version 2 or higher
 */

// Make sure we have built in support for MySQL
if (!function_exists('mysqli_connect'))
	exit('This PHP environment doesn\'t have Improved MySQL (mysqli) support built in. Improved MySQL support is required if you want to use a MySQL 4.1 (or later) database to run this forum. Consult the PHP documentation for further assistance.');

require_once dirname(__FILE__).'/common_params.php';


class DBLayer
{
	var $prefix;
	var $link_id;
	var $query_result;

	var $saved_queries = array();
	var $num_queries = 0;
	var $in_transaction = 0;

	var $error_no = false;
	var $error_msg = 'Unknown';

	var $datatype_transformations = array(
		'%^SERIAL$%'	=>	'INT(10) UNSIGNED AUTO_INCREMENT'
	);


	function __construct($db_host, $db_username, $db_password, $db_name, $db_prefix, $p_connect)
	{
		$this->prefix = $db_prefix;

		// Was a custom port supplied with $db_host?
		if (strpos($db_host, ':') !== false)
			list($db_host, $db_port) = explode(':', $db_host);

		// Persistent connection in MySQLi are only available in PHP 5.3 and later releases
		$p_connect = $p_connect ? 'p:' : '';

		if (isset($db_port))
			$this->link_id = @mysqli_connect($p_connect.$db_host, $db_username, $db_password, $db_name, $db_port);
		else
			$this->link_id = @mysqli_connect($p_connect.$db_host, $db_username, $db_password, $db_name);

		if (!$this->link_id)
			error('Unable to connect to MySQL and select database. MySQL reported: '.mysqli_connect_error(), __FILE__, __LINE__);

		// Setup the client-server character set (UTF-8)
		if (!defined('FORUM_NO_SET_NAMES'))
		{
			if (!$this->set_names('utf8'))
				error('Unable to set the character set.', __FILE__, __LINE__);
		}
	}


	function start_transaction()
	{
		++$this->in_transaction;

		mysqli_query($this->link_id, 'START TRANSACTION');
		return;
	}


	function end_transaction()
	{
		--$this->in_transaction;

		mysqli_query($this->link_id, 'COMMIT');
		return;
	}


	function query($sql, $unbuffered = false)
	{
		if (defined('PUN_SHOW_QUERIES'))
			$q_start = microtime(true);

		$this->query_result = @mysqli_query($this->link_id, $sql);

		if ($this->query_result)
		{
			if (defined('PUN_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, sprintf('%.5F', microtime(true) - $q_start));

			++$this->num_queries;

			return $this->query_result;
		}
		else
		{
			if (defined('PUN_SHOW_QUERIES'))
				$this->saved_queries[] = array($sql, 0);

			$this->error_no = @mysqli_errno($this->link_id);
			$this->error_msg = @mysqli_error($this->link_id);

			// Rollback transaction
			if ($this->in_transaction)
			{
				--$this->in_transaction;

				mysqli_query($this->link_id, 'ROLLBACK');
			}

			return false;
		}
	}


	function query_params($sql, $params = array(), $unbuffered = false)
	{
		$normalized = dbp_normalize_params($params, $error);
		if ($normalized === false)
			return $this->set_param_error($sql, $error);

		if (!dbp_assert_param_count($sql, count($normalized), $error))
			return $this->set_param_error($sql, $error);

		if (defined('PUN_SHOW_QUERIES'))
			$q_start = microtime(true);

		$stmt = @mysqli_prepare($this->link_id, $sql);
		if (!$stmt)
			return $this->set_driver_error($sql, $normalized);

		if (!empty($normalized))
		{
			list($types, $values) = $this->stmt_types_and_values($normalized);
			$bind_args = array_merge(array($stmt, $types), $this->ref_values($values));
			if (!call_user_func_array('mysqli_stmt_bind_param', $bind_args))
			{
				$this->error_no = @mysqli_stmt_errno($stmt);
				$this->error_msg = @mysqli_stmt_error($stmt);
				@mysqli_stmt_close($stmt);

				if (defined('PUN_SHOW_QUERIES'))
					$this->saved_queries[] = array($this->debug_query($sql, $normalized), 0);

				if ($this->in_transaction)
				{
					--$this->in_transaction;
					mysqli_query($this->link_id, 'ROLLBACK');
				}

				return false;
			}
		}

		if (!@mysqli_stmt_execute($stmt))
		{
			$this->error_no = @mysqli_stmt_errno($stmt);
			$this->error_msg = @mysqli_stmt_error($stmt);
			@mysqli_stmt_close($stmt);

			if (defined('PUN_SHOW_QUERIES'))
				$this->saved_queries[] = array($this->debug_query($sql, $normalized), 0);

			if ($this->in_transaction)
			{
				--$this->in_transaction;
				mysqli_query($this->link_id, 'ROLLBACK');
			}

			return false;
		}

		$field_count = mysqli_stmt_field_count($stmt);

		if ($field_count > 0)
		{
			if (function_exists('mysqli_stmt_get_result'))
			{
				$this->query_result = @mysqli_stmt_get_result($stmt);
				if ($this->query_result instanceof mysqli_result)
				{
					@mysqli_stmt_close($stmt);
				}
				else
				{
					$this->query_result = $this->fetch_stmt_buffered_result($stmt);
				}
			}
			else
			{
				$this->query_result = $this->fetch_stmt_buffered_result($stmt);
			}
		}
		else
		{
			$this->query_result = true;
			@mysqli_stmt_close($stmt);
		}

		if (defined('PUN_SHOW_QUERIES'))
			$this->saved_queries[] = array($this->debug_query($sql, $normalized), sprintf('%.5F', microtime(true) - $q_start));

		++$this->num_queries;

		return $this->query_result;
	}


	function result($query_id = 0, $row = 0, $col = 0)
	{
		if ($query_id)
		{
			if ($query_id instanceof DBLayerBufferedResult)
			{
				if (!isset($query_id->rows[$row]['num'][$col]))
					return false;

				return $query_id->rows[$row]['num'][$col];
			}

			if ($row !== 0 && @mysqli_data_seek($query_id, $row) === false)
				return false;

			$cur_row = @mysqli_fetch_row($query_id);
			if ($cur_row === null)
				return false;

			return $cur_row[$col];
		}
		else
			return false;
	}


	function fetch_assoc($query_id = 0)
	{
		if ($query_id instanceof DBLayerBufferedResult)
		{
			if (!isset($query_id->rows[$query_id->row_ptr]))
				return false;

			$row = $query_id->rows[$query_id->row_ptr]['assoc'];
			$query_id->row_ptr++;

			return $row;
		}

		return ($query_id) ? @mysqli_fetch_assoc($query_id) : false;
	}


	function fetch_row($query_id = 0)
	{
		if ($query_id instanceof DBLayerBufferedResult)
		{
			if (!isset($query_id->rows[$query_id->row_ptr]))
				return false;

			$row = $query_id->rows[$query_id->row_ptr]['num'];
			$query_id->row_ptr++;

			return $row;
		}

		return ($query_id) ? @mysqli_fetch_row($query_id) : false;
	}


	function num_rows($query_id = 0)
	{
		if ($query_id instanceof DBLayerBufferedResult)
			return count($query_id->rows);

		return ($query_id) ? @mysqli_num_rows($query_id) : false;
	}


	function affected_rows()
	{
		return ($this->link_id) ? @mysqli_affected_rows($this->link_id) : false;
	}


	function insert_id()
	{
		return ($this->link_id) ? @mysqli_insert_id($this->link_id) : false;
	}


	function get_num_queries()
	{
		return $this->num_queries;
	}


	function get_saved_queries()
	{
		return $this->saved_queries;
	}


	function free_result($query_id = false)
	{
		if ($query_id instanceof DBLayerBufferedResult)
			return true;

		return ($query_id) ? @mysqli_free_result($query_id) : false;
	}


	function escape($str)
	{
		return is_array($str) ? '' : mysqli_real_escape_string($this->link_id, $str);
	}


	function error()
	{
		$result['error_sql'] = empty($this->saved_queries) ? '' : end($this->saved_queries);
		$result['error_no'] = $this->error_no;
		$result['error_msg'] = $this->error_msg;

		return $result;
	}


	function close()
	{
		if ($this->link_id)
		{
			if ($this->query_result instanceof mysqli_result)
				@mysqli_free_result($this->query_result);

			return @mysqli_close($this->link_id);
		}
		else
			return false;
	}


	function stmt_types_and_values($params)
	{
		$types = '';
		$values = array();

		foreach ($params as $value)
		{
			if (is_bool($value) || is_int($value))
				$types .= 'i';
			else if (is_float($value))
				$types .= 'd';
			else
				$types .= 's';

			$values[] = $value;
		}

		return array($types, $values);
	}


	function ref_values(&$arr)
	{
		$refs = array();
		foreach ($arr as $k => $v)
			$refs[$k] = &$arr[$k];

		return $refs;
	}


	function fetch_stmt_buffered_result($stmt)
	{
		$result = new DBLayerBufferedResult();
		$meta = @mysqli_stmt_result_metadata($stmt);

		if (!$meta)
		{
			@mysqli_stmt_close($stmt);
			return $result;
		}

		$fields = array();
		while ($field = @mysqli_fetch_field($meta))
			$fields[] = $field->name;

		$bound = array();
		$bind_refs = array($stmt);
		for ($i = 0; $i < count($fields); $i++)
		{
			$bound[$i] = null;
			$bind_refs[] = &$bound[$i];
		}

		@call_user_func_array('mysqli_stmt_bind_result', $bind_refs);
		@mysqli_stmt_store_result($stmt);

		while (@mysqli_stmt_fetch($stmt))
		{
			$num = array();
			$assoc = array();

			for ($i = 0; $i < count($fields); $i++)
			{
				$num[$i] = $bound[$i];
				$assoc[$fields[$i]] = $bound[$i];
			}

			$result->rows[] = array('num' => $num, 'assoc' => $assoc);
		}

		@mysqli_free_result($meta);
		@mysqli_stmt_close($stmt);

		return $result;
	}


	function debug_query($sql, $params)
	{
		return $sql.' /* params: '.str_replace('*/', '* /', var_export($params, true)).' */';
	}


	function set_param_error($sql, $error)
	{
		$this->error_no = false;
		$this->error_msg = $error;

		if (defined('PUN_SHOW_QUERIES'))
			$this->saved_queries[] = array($this->debug_query($sql, array()), 0);

		if ($this->in_transaction)
		{
			--$this->in_transaction;
			mysqli_query($this->link_id, 'ROLLBACK');
		}

		return false;
	}


	function set_driver_error($sql, $params)
	{
		$this->error_no = @mysqli_errno($this->link_id);
		$this->error_msg = @mysqli_error($this->link_id);

		if (defined('PUN_SHOW_QUERIES'))
			$this->saved_queries[] = array($this->debug_query($sql, $params), 0);

		if ($this->in_transaction)
		{
			--$this->in_transaction;
			mysqli_query($this->link_id, 'ROLLBACK');
		}

		return false;
	}


	function get_names()
	{
		$result = $this->query('SHOW VARIABLES LIKE \'character_set_connection\'');
		return $this->result($result, 0, 1);
	}


	function set_names($names)
	{
		if ('utf8' === $names)
		{
			$names = 'utf8mb4';
		}
		return mysqli_set_charset($this->link_id, $names);
	}


	function get_version()
	{
		$result = $this->query('SELECT VERSION()');

		return array(
			'name'		=> 'MySQL Improved (InnoDB)',
			'version'	=> preg_replace('%^([^-]+).*$%', '\\1', $this->result($result))
		);
	}


	function table_exists($table_name, $no_prefix = false)
	{
		$result = $this->query('SHOW TABLES LIKE \''.($no_prefix ? '' : $this->prefix).$this->escape($table_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function field_exists($table_name, $field_name, $no_prefix = false)
	{
		$result = $this->query('SHOW COLUMNS FROM '.($no_prefix ? '' : $this->prefix).$table_name.' LIKE \''.$this->escape($field_name).'\'');
		return $this->num_rows($result) > 0;
	}


	function index_exists($table_name, $index_name, $no_prefix = false)
	{
		$exists = false;

		$result = $this->query('SHOW INDEX FROM '.($no_prefix ? '' : $this->prefix).$table_name);
		while ($cur_index = $this->fetch_assoc($result))
		{
			if (strtolower($cur_index['Key_name']) == strtolower(($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name))
			{
				$exists = true;
				break;
			}
		}

		return $exists;
	}


	function create_table($table_name, $schema, $no_prefix = false)
	{
		if ($this->table_exists($table_name, $no_prefix))
			return true;

		$query = 'CREATE TABLE '.($no_prefix ? '' : $this->prefix).$table_name." (\n";

		// Go through every schema element and add it to the query
		foreach ($schema['FIELDS'] as $field_name => $field_data)
		{
			$field_data['datatype'] = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_data['datatype']);

			$query .= $field_name.' '.$field_data['datatype'];

			if (isset($field_data['collation']))
				$query .= 'CHARACTER SET utf8mb4 COLLATE utf8mb4_'.$field_data['collation'];

			if (!$field_data['allow_null'])
				$query .= ' NOT NULL';

			if (isset($field_data['default']))
				$query .= ' DEFAULT '.$field_data['default'];

			$query .= ",\n";
		}

		// If we have a primary key, add it
		if (isset($schema['PRIMARY KEY']))
			$query .= 'PRIMARY KEY ('.implode(',', $schema['PRIMARY KEY']).'),'."\n";

		// Add unique keys
		if (isset($schema['UNIQUE KEYS']))
		{
			foreach ($schema['UNIQUE KEYS'] as $key_name => $key_fields)
				$query .= 'UNIQUE KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$key_name.'('.implode(',', $key_fields).'),'."\n";
		}

		// Add indexes
		if (isset($schema['INDEXES']))
		{
			foreach ($schema['INDEXES'] as $index_name => $index_fields)
				$query .= 'KEY '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.'('.implode(',', $index_fields).'),'."\n";
		}

		// We remove the last two characters (a newline and a comma) and add on the ending
		$query = substr($query, 0, strlen($query) - 2)."\n".') ENGINE = '.(isset($schema['ENGINE']) ? $schema['ENGINE'] : 'InnoDB').' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';

		return $this->query($query) ? true : false;
	}


	function drop_table($table_name, $no_prefix = false)
	{
		if (!$this->table_exists($table_name, $no_prefix))
			return true;

		return $this->query('DROP TABLE '.($no_prefix ? '' : $this->prefix).$table_name) ? true : false;
	}


	function rename_table($old_table, $new_table, $no_prefix = false)
	{
		// If the new table exists and the old one doesn't, then we're happy
		if ($this->table_exists($new_table, $no_prefix) && !$this->table_exists($old_table, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$old_table.' RENAME TO '.($no_prefix ? '' : $this->prefix).$new_table) ? true : false;
	}


	function add_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if ($this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if (!is_null($default_value) && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.$field_name.' '.$field_type.($allow_null ? '' : ' NOT NULL').(!is_null($default_value) ? ' DEFAULT '.$default_value : '').(!is_null($after_field) ? ' AFTER '.$after_field : '')) ? true : false;
	}


	function alter_field($table_name, $field_name, $field_type, $allow_null, $default_value = null, $after_field = null, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		$field_type = preg_replace(array_keys($this->datatype_transformations), array_values($this->datatype_transformations), $field_type);

		if (!is_null($default_value) && !is_int($default_value) && !is_float($default_value))
			$default_value = '\''.$this->escape($default_value).'\'';

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' MODIFY '.$field_name.' '.$field_type.($allow_null ? '' : ' NOT NULL').(!is_null($default_value) ? ' DEFAULT '.$default_value : '').(!is_null($after_field) ? ' AFTER '.$after_field : '')) ? true : false;
	}


	function drop_field($table_name, $field_name, $no_prefix = false)
	{
		if (!$this->field_exists($table_name, $field_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP '.$field_name) ? true : false;
	}


	function add_index($table_name, $index_name, $index_fields, $unique = false, $no_prefix = false)
	{
		if ($this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' ADD '.($unique ? 'UNIQUE ' : '').'INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name.' ('.implode(',', $index_fields).')') ? true : false;
	}


	function drop_index($table_name, $index_name, $no_prefix = false)
	{
		if (!$this->index_exists($table_name, $index_name, $no_prefix))
			return true;

		return $this->query('ALTER TABLE '.($no_prefix ? '' : $this->prefix).$table_name.' DROP INDEX '.($no_prefix ? '' : $this->prefix).$table_name.'_'.$index_name) ? true : false;
	}

	function truncate_table($table_name, $no_prefix = false)
	{
		return $this->query('TRUNCATE TABLE '.($no_prefix ? '' : $this->prefix).$table_name) ? true : false;
	}
}
