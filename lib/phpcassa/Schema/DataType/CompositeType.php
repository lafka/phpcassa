<?php
namespace phpcassa\Schema\DataType;

/**
 * @package phpcassa\Schema\DataType
 */
class CompositeType extends CassandraType
{
    public function __construct($inner_types) {
        $this->inner_types = $inner_types;
    }

    public function pack($value, $slice_end=null) {
        $res = "";
        for ($i = 0; $i < count($value); $i++) {
            $eoc = 0x00;
            $item = $value[$i];
            if (is_array($item)) {
                list($item, $inclusive) = $item;
                if ($inclusive) {
                    if ($slice_end == self::SLICE_START) {
                        $eoc = 0xFF;
                    }
                    elseif ($slice_end == self::SLICE_FINISH) {
                        $eoc = 0x01;
                    }
                }
                else {
                    if ($slice_end == self::SLICE_START) {
                        $eoc = 0x01;
                    }
                    elseif ($slice_end == self::SLICE_FINISH) {
                        $eoc = 0xFF;
                    }
                }
            }
            $type = $this->inner_types[$i];
            $packed = $type->pack($item);
            $len = strlen($packed);
            $res .= pack("C2", $len&0xFF00, $len&0xFF).$packed.pack("C1", $eoc);
        }

        return $res;
    }

    public function unpack($data) {
        $component_idx = 0;
        $components = array();
        while (empty($data) !== true) {
            $bytes = unpack("Chi/Clow", substr($data, 0, 2));
            $len = $bytes["hi"]*256 + $bytes["low"];
            $component_data = substr($data, 2, $len);

            $type = $this->inner_types[$component_idx];
            $unpacked = $type->unpack($component_data);
            $components[] = $unpacked;

            $data = substr($data, $len + 3);
            $component_idx++;
        }

        return serialize($components);
    }

    public function __toString() {
        $inner_strs = array();
        foreach ($inner_types as $inner_type) {
            $inner_strs[] = (string)$inner_type;
        }

        return 'CompositeType(' . join(', ', $inner_strs) . ')';
    }
}