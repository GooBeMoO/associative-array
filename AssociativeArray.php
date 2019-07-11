<?php

/**
 * Associative array class.
 *
 * @author  Nick Lai <resxc13579@gmail.com>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/nick-lai/associative-array
 */

namespace NickLai;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

class AssociativeArray implements ArrayAccess, Countable, IteratorAggregate
{
    /**
     * The rows contained in the associative array.
     *
     * @var array
     */
    protected $rows = [];

    /**
     * Create a new associative array.
     *
     * @param mixed $rows
     * @return void
     */
    public function __construct($rows = [])
    {
        $this->rows = $this->getAssociativeRows($rows);
    }

    /**
     * Create a new associative array instance.
     *
     * @param mixed $rows
     * @return static
     */
    public static function make($rows = [])
    {
        return new static($rows);
    }

    /**
     * Get rows of selected columns.
     * 取出需要的欄位
     * @param string|array $keys
     * @return static
     */
    public function select($keys)
    {
        // 是否為array
        if (!is_array($keys)) {
            // 強制轉換
            $keys = (array)$keys;
        }
        // key <=> value
        $keys = array_flip($keys);

        // 使用外部變數$keys 取得相同key後回傳rows
        return new static(array_map(function($row) use ($keys) {
            // 回傳相同key 
            return array_intersect_key($row, $keys);
        }, $this->rows));
    }

    /**
     * Filter the rows using the given callback.
     * 回傳過濾後的條件
     * 
     * @param callable $callback
     * @return static
     */
    public function where(callable $callback)
    {
        // 回傳key以及value
        return new static(array_filter($this->rows, $callback, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * Inner join rows
     * 交集rows
     * @param array $rows
     * @param callable $on
     * @return static
     */
    public function innerJoin($rows, callable $on)
    {
        $result = [];

        // $this->rows 為$associativeArray($data)
        foreach ($this->rows as $leftRow) {
            // $rows 為要被合併的array
            foreach ($rows as $rightRow) {
                // 自訂的function 成立時
                if ($on($leftRow, $rightRow)) {
                    $result[] = $leftRow + $rightRow;
                    break;
                }
            }
        }

        return new static($result);
    }

    /**
     * Left join rows
     *
     * @param array $rows
     * @param callable $on
     * @return static
     */
    public function leftJoin($rows, callable $on)
    {
        $nullRightRow = [];

        foreach ((new static($rows))->first() as $key => $value) {
            $nullRightRow[$key] = null;
        }

        $result = [];

        foreach ($this->rows as $leftRow) {
            $row = $leftRow + $nullRightRow;
            foreach ($rows as $rightRow) {
                if ($on($leftRow, $rightRow)) {
                    $row = $leftRow + $rightRow;
                    break;
                }
            }
            $result[] = $row;
        }

        return new static($result);
    }

    /**
     * Right join rows
     *
     * @param array $rows
     * @param callable $on
     * @return static
     */
    public function rightJoin($rows, callable $on)
    {
        return (new static($rows))->leftJoin($this->rows, $on);
    }

    /**
     * Order by keys
     *
     * @param string|array $keys
     * @param string|array $directions
     * @return static
     */
    public function orderBy($keys, $directions = 'asc')
    {
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }

        $key2IsDesc = [];

        if (is_string($directions)) {
            $isDesc = $directions === 'desc';
            foreach ($keys as $key) {
                $key2IsDesc[$key] = $isDesc;
            }
        } else {
            $i = 0;
            foreach ($keys as $key) {
                $key2IsDesc[$key] = (($directions[$i++] ?? 'asc') === 'desc');
            }
        }

        $result = $this->rows;

        usort($result, function($a, $b) use ($keys, $key2IsDesc) {
            foreach ($keys as $key) {
                if ($comparedResult = $key2IsDesc[$key]
                        ? $b[$key] <=> $a[$key]
                        : $a[$key] <=> $b[$key]) {
                    return $comparedResult;
                }
            }
            return 0;
        });

        return new static($result);
    }

    /**
     * Groups an associative array by keys.
     *
     * @param array|string $keys
     * @return static
     */
    public function groupBy($keys)
    {
        if (!is_array($keys)) {
            $keys = (array)$keys;
        }

        $result = [];

        foreach ($this->rows as $row) {
            $groupKey = implode(',', array_intersect_key($row, array_flip($keys)));
            if (!isset($result[$groupKey])) {
                $result[$groupKey] = $row;
            }
        }

        return new static(array_values($result));
    }

    /**
     * Return the first row
     * 回傳第一列
     * @param mixed $default
     * @return mixed
     */
    public function first($default = null)
    {
        // 取出第一次的row
        foreach ($this->rows as $row) {
            return $row;
        }

        return $default;
    }

    /**
     * Return the last row
     *
     * @param mixed $default
     * @return mixed
     */
    public function last($default = null)
    {
        // 倒序array後 第一次row
        foreach (array_reverse($this->rows) as $row) {
            return $row;
        }

        return $default;
    }

    /**
     * Count the number of rows in the associative array.
     * 回傳array中元素數量
     * @return int
     */
    public function count()
    {
        return count($this->rows);
    }

    /**
     * Get the sum of a given key.
     * 取得指定鍵的總和
     * @param string $key
     * @return mixed
     */
    public function sum($key)
    {
        // 從陣列中取出需要的key->將key中的value加總起來
        return array_sum(array_column($this->rows, $key));
    }

    /**
     * Get the average value of a given key.
     * 取得指定鍵的平均
     * 
     * @param string $key
     * @return mixed
     */
    public function avg($key)
    {
        // 先透過sum()取得總數
        $sum = $this->sum($key);
        // 透過count()取得元素數量 $sum存在  sum()/count() 不存在 值接回傳 $sum
        return $sum ? ($sum / $this->count()) : $sum;
    }

    /**
     * Get the instance as an array.
     * 物件轉為array
     * @return array
     */
    public function toArray()
    {
        return array_map(function($row) {
            // 檢查是否為屬於此class
            return $row instanceof self ? $row->toArray() : $row;
        }, $this->rows);
    }

    /**
     * Get an iterator for the rows.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new ArrayIterator($this->rows);
    }

    /**
     * Determine if a row exists at an offset.
     *
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->rows);
    }

    /**
     * Get a row at a given offset.
     *
     * @param mixed $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->rows[$offset];
    }

    /**
     * Set the row at a given offset.
     *
     * @param mixed $offset
     * @param mixed $row
     * @return void
     */
    public function offsetSet($offset, $row)
    {
        if (is_null($offset)) {
            $this->rows[] = $row;
        } else {
            $this->rows[$offset] = $row;
        }
    }

    /**
     * Unset the row at a given offset.
     *
     * @param string $offset
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->rows[$offset]);
    }

    /**
     * Results array of rows from associative array or traversable.
     *
     * @param mixed $rows
     * @return array
     */
    protected function getAssociativeRows($rows)
    {
        if (is_array($rows)) {
            return $rows;
        } elseif ($rows instanceof self) {
            return $rows->toArray();
        } elseif ($rows instanceof Traversable) {
            return iterator_to_array($rows);
        }

        return (array)$rows;
    }
}
