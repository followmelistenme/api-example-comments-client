<?php


namespace ExCom\Domain\Comments\DTOs;


class CommentCreateDTO implements \JsonSerializable
{
    /** @var string */
    private $name;

    /** @var string */
    private $text;

    public function __construct(string $name, string $text)
    {
        $this->name = $name;
        $this->text = $text;
    }

    public function jsonSerialize()
    {
        return [
            'name' => $this->name,
            'text' => $this->text,
        ];
    }
}
