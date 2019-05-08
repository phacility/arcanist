<?php

final class UberRefProvider {
    private $are_custom_refs_enabled;

    function __construct($is_non_tag_ref_enabled) {
        $this->are_custom_refs_enabled = $is_non_tag_ref_enabled;
    }

    public function getBaseRefName($prefix, $id, $current_value = null) {
        if ($this->are_custom_refs_enabled) {
            return "refs/{$prefix}/base/{$id}";
        } else {
            if ($current_value == null) {
                return "refs/tags/{$prefix}/base/{$id}";
            } else {
                return $current_value;
            }
        }
    }

    public function getDiffRefName($prefix, $id, $current_value = null) {
        if ($this->are_custom_refs_enabled) {
            return "refs/{$prefix}/diff/{$id}";
        } else {
            if ($current_value == null) {
                return "refs/tags/{$prefix}/diff/{$id}";
            } else {
                return $current_value;
            }
        }
    }
}
