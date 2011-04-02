<?php
/**
 * Signature to SVG Image: a supplemental server-side script for "Signature Pad"
 * that generates a scalable vector graphic (SVG) of the Signature Pad's JSON output.
 * PHP 5.3 and above required.  No other external dependencies (like GD).
 *
 * @author	Chaz <chaz_meister_rock@yahoo.com>
 * @package	sigToSvg
 * @link	http://thomasjbradley.ca/lab/signature-pad
 * @license	BSD Simplified
 * @version	1.0
 */

/**
 * Accepts a signature created by signature pad in Json format and constructs an SVG
 * XML document for output.
 * <code>
 * <?php
 *	try {
 *		$sig = '[{"lx":45,"ly":42,"mx":45,"my":72},{"lx":41,"ly":36,"mx":95,"my":42},{"lx":77,"ly":28,"mx":41,"my":36}]';
 *		// $sig = json_decode($sig); // can accept either JSON string or the native PHP decoded array.
 *		$svg = new sigToSvg($sig, array('penWidth' => 5));
 *		header('Content-Type: ' . sigToSvg::getMimeType());
 *		echo $svg->getImage();
 *	} catch (Exception $e) {
 *		die($e->getMessage());
 * }
 * </code>
 */
Class sigToSvg {

	/**
	 * Associative array of options.
	 * @var array|null
	 */
	private $options;

	/**
	 * An array of indexed coordinates [lx, ly, mx, my]
	 * @var array|null
	 */
	private $coords;

	/**
	 * Maximum image width and height.
	 * @var array
	 */
	private $max = array(0, 0);

	/**
	 * @param	string|array	$json Can accept a JSON string or an array of SigPad coord objects.
	 * @param	array			$options
	 *			title			: @var string ['Signature'] Text description of the image
	 * 			penWidth		: @var int [2] width of the line
	 * 			penColour		: @var string ['#145394'] hexidecimal color of the signature
	 * @throws	Exception If failure on JSON parsing.
	 */
	public function __construct($json, $options = array()) {
		$this->options = array_merge($this->getDefaultOptions(), $options);
		if (is_string($json)) {
			$this->coords = json_decode($json, true); // force to assoc array
			if (is_null($this->coords)) {
				$jErr = '';
				if (function_exists('json_last_error')) { // allow for php 5.2
					switch(json_last_error()) {
						case JSON_ERROR_DEPTH:
								$jErr = ' - Maximum stack depth exceeded';
						break;
						case JSON_ERROR_CTRL_CHAR:
								$jErr = ' - Unexpected control character found';
						break;
						case JSON_ERROR_SYNTAX:
								$jErr = ' - Syntax error, malformed JSON';
						break;
						case JSON_ERROR_NONE:
								$jErr = ' - Unknown error';
						break;
					}
				}
				throw new Exception("Cannot decode the JSON string.$jErr", 1000);
			}
			$this->coords = array_map('array_values', $this->coords); // flatten the array
		} elseif (is_array($json)) {
			$this->coords = array();
			foreach ($json as $obj) $this->coords[] = array_values((array)$obj);
		} else {
			throw new Exception('Data passed to constructor is invalid.', 1001);
		}
	}

	/**
	 * Svg Mime Type
	 * @return string
	 */
	static public function getMimeType() {
		return 'image/svg+xml';
	}

	/**
	 * @return array Name value pairs
	 */
	private function getDefaultOptions() {
		return array(
			'title'                 => 'Signature',
			'penWidth'              => 2,
			'penColour'             => '#145394'
		);
	}

	/**
	 * Determine the maximum height and width of the image.
	 * @param array $coord
	 * @return null
	 */
	private function setMax($coord) {
		foreach ($coord as $i => $pt) {
			if ($pt > $this->max[$i%2]) $this->max[$i%2] = $pt;
		}       
	}

	/**
	 * Get the SVG line elements.
	 * @return string
	 */
	private function getLineElements() {
		$lines = '';
		foreach ($this->coords as $coord) {
			$lines .= vsprintf('<line x1="%d" y1="%d" x2="%d" y2="%d"/>', $coord);
			$this->setMax($coord);
		}
		return $lines;
	}

	/**
	 * Get the image boundaries.
	 * @param bool $axis False is x-axis, True is y-axis
	 * @return int
	 */
	private function getBound($axis=0) {
		return round($this->max[(int)$axis] + ($this->options['penWidth'] / 2));
	}

	/**
	 * Get the full XML SVG image.
	 * @return string
	 */
	public function getImage() {
		$lines = $this->getLineElements();
		return '<?xml version="1.0"?><svg baseProfile="tiny" width="' . $this->getBound(0) . '" height="' . $this->getBound(1) . '" version="1.2" xmlns="http://www.w3.org/2000/svg"><g fill="red" stroke="' . $this->options['penColour'] . '" stroke-width="' . (int)$this->options['penWidth'] . '" stroke-linecap="round" stroke-lingjoin="round"><title>' . htmlspecialchars($this->options['title']) . '</title>' . $lines . '</g></svg>';
	}

	/**
	 * Compress the SVG using gzip.
	 * @return binary
	 */
	public function getImageGz() {
		if (!function_exists('gzencode')) throw new Exception('Cannot get gzip image. Check that Zlib is installed.', 2000);
		return gzencode($this->getImage(), 9);
	}
}

