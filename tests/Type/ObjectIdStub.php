<?php
namespace GraphQL\Tests\Type;

class ObjectIdStub
{
	/**
	 * @var int
	 */
	private $id;

	/**
	 * @param int $id
	 */
	public function __construct($id)
	{
		$this->id = $id;
	}

	public function __toString()
	{
		return (string) $this->id;
	}
}
