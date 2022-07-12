<?php

namespace App\Dto;

use App\Enum\CourseType;
use JMS\Serializer\Annotation as Serializer;
use OpenApi\Annotations as OA;
use Symfony\Component\Validator\Constraints as Assert;

class CourseDto
{
    /**
     * @Serializer\Groups({"info", "new_edit"})
     * @Assert\NotNull(groups={"new_edit"})
     */
    private string $code;

    /**
     * @Serializer\Groups({"new_edit"})
     * @Assert\NotNull(groups={"new_edit"})
     */
    private string $name;

    /**
     * @OA\Property(type="string")
     * @Serializer\Groups({"info", "new_edit"})
     * @Assert\NotNull(groups={"new_edit"})
     * @Assert\Choice(CourseType::NAMES)
     */
    private string $type;

    /**
     * @Serializer\Groups({"info", "new_edit"})
     */
    private float $price = 0.0;

    /**
     * @Assert\IsTrue(groups={"new_edit"}, message="Price field required for course which type is not free")
     */
    public function isPricePresented()
    {
        if ($this->type !== CourseType::NAMES[CourseType::FREE]) {
            return $this->price > 0.0;
        }
        return true;
    }

    /**
     * @return string
     */
    public function getCode(): string
    {
        return $this->code;
    }

    /**
     * @param string $code
     */
    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }

    /**
     * @return float
     */
    public function getPrice(): float
    {
        return $this->price;
    }

    /**
     * @param float $price
     */
    public function setPrice(float $price): void
    {
        $this->price = $price;
    }
}
