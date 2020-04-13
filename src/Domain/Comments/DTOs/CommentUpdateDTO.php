<?php


namespace ExCom\Domain\Comments\DTOs;


class CommentUpdateDTO implements \JsonSerializable
{
    /** @var int */
    private $ID;

    /** @var string */
    private $name;

    /** @var string */
    private $text;

    public function __construct(int $ID, ?string $name, ?string $text)
    {
        $this->ID = $ID;
        $this->name = $name;
        $this->text = $text;
    }

    public function getID(): int
    {
        return $this->ID;
    }

    public function jsonSerialize()
    {
        return array_filter(
            [
                'name' => $this->name,
                'text' => $this->text,
            ],
            function ($value) {
                return !is_null($value);
            }
        );
    }
}
