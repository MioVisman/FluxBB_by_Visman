<?php

/**
 * Shared helpers for parameterized queries in DB layer implementations.
 */

if (!class_exists('DBLayerBufferedResult'))
{
	class DBLayerBufferedResult
	{
		var $rows = array();
		var $row_ptr = 0;
	}
}

if (!function_exists('dbp_normalize_params'))
{
	function dbp_normalize_params($params, &$error = null)
	{
		$error = null;

		if (!is_array($params))
		{
			$error = 'Query parameters must be an array.';
			return false;
		}

		$normalized = array();
		foreach ($params as $index => $value)
		{
			if (is_array($value) || is_object($value) || is_resource($value))
			{
				$error = 'Unsupported parameter type at index '.$index.'.';
				return false;
			}

			$normalized[] = $value;
		}

		return $normalized;
	}
}

if (!function_exists('dbp_assert_param_count'))
{
	function dbp_assert_param_count($sql, $count, &$error = null)
	{
		$error = null;
		$expected = dbp_scan_placeholder_count($sql);

		if ($expected !== $count)
		{
			$error = 'Parameter count mismatch: expected '.$expected.', got '.$count.'.';
			return false;
		}

		return true;
	}
}

if (!function_exists('dbp_pgsql_rewrite_placeholders'))
{
	function dbp_pgsql_rewrite_placeholders($sql)
	{
		return dbp_scan_placeholder_count($sql, true);
	}
}

if (!function_exists('dbp_scan_placeholder_count'))
{
	function dbp_scan_placeholder_count($sql, $rewrite = false)
	{
		$len = strlen($sql);
		$out = '';
		$count = 0;

		for ($i = 0; $i < $len; $i++)
		{
			$ch = $sql[$i];
			$next = ($i + 1 < $len) ? $sql[$i + 1] : '';

			if ($ch === '\'' || $ch === '"')
			{
				$quote = $ch;
				if ($rewrite) $out .= $ch;
				$i++;

				while ($i < $len)
				{
					$c = $sql[$i];
					if ($rewrite) $out .= $c;

					if ($c === '\\')
					{
						if ($i + 1 < $len)
						{
							$i++;
							if ($rewrite) $out .= $sql[$i];
						}
						$i++;
						continue;
					}

					if ($c === $quote)
					{
						if ($i + 1 < $len && $sql[$i + 1] === $quote)
						{
							$i++;
							if ($rewrite) $out .= $sql[$i];
							$i++;
							continue;
						}
						break;
					}

					$i++;
				}

				continue;
			}

			if ($ch === '-' && $next === '-')
			{
				if ($rewrite) $out .= '--';
				$i += 2;
				while ($i < $len)
				{
					$c = $sql[$i];
					if ($rewrite) $out .= $c;
					if ($c === "\n" || $c === "\r")
						break;
					$i++;
				}
				continue;
			}

			if ($ch === '/' && $next === '*')
			{
				if ($rewrite) $out .= '/*';
				$i += 2;
				while ($i < $len)
				{
					$c = $sql[$i];
					$n = ($i + 1 < $len) ? $sql[$i + 1] : '';
					if ($rewrite) $out .= $c;
					if ($c === '*' && $n === '/')
					{
						if ($rewrite) $out .= '/';
						$i++;
						break;
					}
					$i++;
				}
				continue;
			}

			if ($ch === '?')
			{
				$count++;
				if ($rewrite)
					$out .= '$'.$count;
				continue;
			}

			if ($rewrite)
				$out .= $ch;
		}

		return $rewrite ? $out : $count;
	}
}
