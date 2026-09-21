<?php

namespace ELD\Adapters;

if (!defined('ABSPATH')) {
	exit;
}

interface AdapterInterface
{
	/**
	 * @param array $item Parsed item DTO
	 * @return array{id:int,status:string,link:string,message:string}
	 */
	public function upsert(array $item);
}
