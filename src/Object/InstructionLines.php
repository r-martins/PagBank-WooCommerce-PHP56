<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class InstructionLines
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class InstructionLines implements JsonSerializable
{
    private $line_1;
    private $line_2;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getLine1() {
        return $this->line_1;
    }

    /**
     * @param string $line_1
     */
    public function setLine1($line_1) {
        $this->line_1 = substr($line_1, 0, 75);
    }

    /**
     * @return string
     */
    public function getLine2() {
        return $this->line_2;
    }

    /**
     * @param string $line_2
     */
    public function setLine2($line_2) {
        $this->line_2 = substr($line_2, 0, 75);
    }

}
