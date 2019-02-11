<?php

class CRM_MembershipExtras_Helper_MembershipTypeSettings {

  const COLOUR_SETTINGS_KEY = 'membership_type_colour';

  /**
   * Receives a background color in hexadecimal format and determines
   * what the text colour should be based on the intensity of the background
   * colour. Returns black or white in hex format.
   *
   * @param string $hex
   *
   * @return string
   */
  public function computeTextColor($hex) {
    if ($hex == 'inherit') {
      return 'inherit';
    }

    list($r, $g, $b) = array_map('hexdec', str_split(trim($hex, '#'), 2));
    $uiColours = [$r / 255, $g / 255, $b / 255];
    $c = array_map(array($this,'calcColour'), $uiColours);

    $luminance = (0.2126 * $c[0]) + (0.7152 * $c[1]) + (0.0722 * $c[2]);

    return ($luminance > 0.179) ? '#000000' : '#ffffff';
  }

  /**
   * Calculate colour for RGB values.
   *
   * @param string $c
   *
   * @return float|int
   */
  private function calcColour($c) {
    if ($c <= 0.03928) {
      return $c / 12.92;
    }
    else {
      return pow(($c + 0.055) / 1.055, 2.4);
    }
  }
}
