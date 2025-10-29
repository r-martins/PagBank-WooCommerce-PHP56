<?php
/** @noinspection PhpUnused */

namespace RM_PagBank\Object;

use JsonSerializable;

/**
 * Class Boleto
 *
 * @author    Ricardo Martins
 * @copyright 2023 Magenteiro
 * @package   RM_PagBank\Object
 */
class Boleto implements JsonSerializable
{
    private $due_date;
    private $instruction_lines;
    private $holder;

        public function jsonSerialize()
    {
        return get_object_vars($this);
    }

    /**
     * @return string
     */
    public function getDueDate() {
        return $this->due_date;
    }

    /**
     * @param string $due_date yyyy-MM-dd
     */
    public function setDueDate($due_date) {
        $this->due_date = $due_date;
    }

    /**
     * @return InstructionLines
     */
    public function getInstructionLines() {
        return $this->instruction_lines;
    }

    /**
     * @param InstructionLines $instruction_lines
     */
    public function setInstructionLines(InstructionLines $instruction_lines) {
        $this->instruction_lines = $instruction_lines;
    }

    /**
     * @return Holder
     */
    public function getHolder() {
        return $this->holder;
    }

    /**
     * @param Holder $holder
     */
    public function setHolder(Holder $holder) {
        $this->holder = $holder;
    }

}
