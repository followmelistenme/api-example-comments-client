<?php


namespace ExCom\Domain\Comments;

class Comment
{
    public const VALIDATION_MAP = [
        'id' => '\is_int',
        'name' => '\is_string',
        'text' => '\is_string',
    ];

    /** @var int|null */
    private $ID;

    /** @var string */
    private $name;

    /** @var string */
    private $text;

    private function __construct(int $ID, string $name, string $text)
    {
        $this->ID = $ID;
        $this->name = $name;
        $this->text = $text;
    }

    public static function createFromResponse(array $comment): self
    {
        return new self($comment['id'], $comment['name'], $comment['text']);
    }

    public function getID(): int
    {
        return $this->ID;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->ID,
            'name' => $this->name,
            'text' => $this->text,
        ];
    }
}
