<?php
 class Requests_Utility_FilteredIterator extends ArrayIterator { protected $callback; public function __construct($data, $callback) { parent::__construct($data); $this->callback = $callback; } public function current() { $value = parent::current(); if (is_callable($this->callback)) { $value = call_user_func($this->callback, $value); } return $value; } public function unserialize($serialized) {} public function __unserialize($serialized) {} public function __wakeup() { unset($this->callback); } } 