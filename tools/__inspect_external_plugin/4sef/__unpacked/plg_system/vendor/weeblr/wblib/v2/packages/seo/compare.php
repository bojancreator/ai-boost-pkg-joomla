<?php
/**
 * Project:                 4SEF
 *
 * @package                 4SEF
 * @copyright               Copyright Weeblr llc - 2022 -2025
 * @author                  Yannick Gaultier - Weeblr llc
 * @license                 GNU General Public License version 3; see LICENSE.md
 * @version                 @build_version_full_build@
 *
 * 2025-06-02
 */

namespace Weeblr\Wblib\Forsef\Seo;

use Weeblr\Wblib\Forsef\Wb;

// Security check to ensure this file is being included by a parent file.
defined('_JEXEC') || defined('WBLIB_Forsef_ROOT_PATH') || die;

/**
 * Compare two strings to evaluate how similar they are.
 *
 * Derived from code published on Stackoverflow by user joshweir
 *
 * https://stackoverflow.com/questions/4898705/smith-waterman-for-string-in-php/38235828#38235828
 * https://stackoverflow.com/questions/16925150/php-string-comparison-and-similarity-index/38236357#38236357
 *
 */
class Compare
{
	/**
	 * Comparison methods
	 */
	const SMITH_WATERMAN_GOTOH = 0;
	const JARO_WINKLER = 1;

	/**
	 * @var int Default method is Smith Waterman Gotoh
	 */
	private $method = self::SMITH_WATERMAN_GOTOH;

	/**
	 * @var float Gap penalty
	 */
	private $gapValue = -0.5;

	/**
	 * @var float Match value
	 */
	private $matchValue = 1.0;

	/**
	 * @var float Mismatch value
	 */
	private $mismatchValue = -2.0;

	/**
	 * @var float
	 */
	private $prefixScale = 0.1;

	/**
	 * Constructs a new Smith Waterman metric.
	 *
	 * @param   int    $method
	 * @param   array  $options
	 *
	 * @throws \Exception
	 */
	public function __construct($method = self::SMITH_WATERMAN_GOTOH, $options = [])
	{
		if (self::SMITH_WATERMAN_GOTOH === $method)
		{
			$this->gapValue = Wb\arrayGet($options, 'gapValue', -0.5);
			if ($this->gapValue > 0.0)
			{
				throw new \Exception(__METHOD__ . ', gapValue must be <= 0');
			}

			$this->matchValue    = Wb\arrayGet($options, 'matchValue', 1.0);
			$this->mismatchValue = Wb\arrayGet($options, 'mismatchValue', -2.0);
			if ($this->matchValue <= $this->mismatchValue)
			{
				throw new \Exception(__METHOD__ . ', mismatchValue must be > matchValue');
			}

		}
		else if (self::JARO_WINKLER === $method)
		{
			$this->prefixScale = Wb\arrayGet($options, 'prefixScale', 0.1);
			if ($this->prefixScale <= 0)
			{
				throw new \Exception(__METHOD__ . ', prefixScale must be > 0');
			}
		}
		else
		{
			throw new \Exception(__METHOD__ . ', invalid comparison method passed.');
		}
	}

	/**
	 * Compute a similarity percentage value between the 2 strings passed.
	 *
	 * String can be of any length and include UTF-8 encoded caracters.
	 *
	 * @param   string  $a
	 * @param   string  $b
	 *
	 * @return float
	 * @throws \Exception
	 */
	public function compare($a, $b)
	{
		if (empty($a) && empty($b))
		{
			return 1.0;
		}

		if (empty($a) || empty($b))
		{
			return 0.0;
		}

		switch ($this->method)
		{
			case self::SMITH_WATERMAN_GOTOH:
				return $this->swgCompare($a, $b);
			case self::JARO_WINKLER:
				return $this->jwCompare($a, $b);
		}

		throw new \Exception(__METHOD__ . ', invalid comparison method passed.');
	}

	/**
	 * Implement the Smith Waterman Gotoh strings comparison method.
	 *
	 * @param   string  $a
	 * @param   string  $b
	 *
	 * @return float
	 */
	private function swgCompare($a, $b)
	{
		$maxDistance =
			min(
				mb_strlen($a),
				mb_strlen($b)
			)
			*
			max(
				$this->matchValue,
				$this->gapValue
			);

		return $this->swgCompute($a, $b) / $maxDistance;
	}

	private function swgCompute($s, $t)
	{
		$v0    = [];
		$v1    = [];
		$t_len = mb_strlen($t);
		$max   = $v0[0] = max(0, $this->gapValue, $this->swgComputeMatchCompare($s, 0, $t, 0));

		for ($j = 1; $j < $t_len; $j++)
		{
			$v0[$j] = max(0, $v0[$j - 1] + $this->gapValue,
				$this->swgComputeMatchCompare($s, 0, $t, $j));

			$max = max($max, $v0[$j]);
		}

		// Find max
		for ($i = 1; $i < mb_strlen($s); $i++)
		{
			$v1[0] = max(0, $v0[0] + $this->gapValue, $this->swgComputeMatchCompare($s, $i, $t, 0));

			$max = max($max, $v1[0]);

			for ($j = 1; $j < $t_len; $j++)
			{
				$v1[$j] = max(0, $v0[$j] + $this->gapValue, $v1[$j - 1] + $this->gapValue,
					$v0[$j - 1] + $this->swgComputeMatchCompare($s, $i, $t, $j));

				$max = max($max, $v1[$j]);
			}

			for ($j = 0; $j < $t_len; $j++)
			{
				$v0[$j] = $v1[$j];
			}
		}

		return $max;
	}

	private function swgComputeMatchCompare($a, $aIndex, $b, $bIndex)
	{
		return $a[$aIndex] === $b[$bIndex]
			? $this->matchValue
			: $this->mismatchValue;
	}

	/**
	 * Implement the Jaro Winkler strings comparison method.
	 *
	 * @param   string  $a
	 * @param   string  $b
	 *
	 * @return float
	 */
	private function jwCompare($string1, $string2)
	{
		$JaroDistance = $this->jwCompute($string1, $string2);
		$prefixLength = $this->getPrefixLength($string1, $string2);

		return $JaroDistance + $prefixLength * $this->prefixScale * (1.0 - $JaroDistance);
	}

	private function jwCompute($string1, $string2)
	{
		$str1_len = mb_strlen($string1);
		$str2_len = mb_strlen($string2);

		// theoretical distance
		$distance = (int) floor(min($str1_len, $str2_len) / 2.0);

		// get common characters
		$commons1 = $this->getCommonCharacters($string1, $string2, $distance);
		$commons2 = $this->getCommonCharacters($string2, $string1, $distance);

		if (($commons1_len = mb_strlen($commons1)) == 0) return 0;
		if (($commons2_len = mb_strlen($commons2)) == 0) return 0;
		// calculate transpositions
		$transpositions = 0;
		$upperBound     = min($commons1_len, $commons2_len);
		for ($i = 0; $i < $upperBound; $i++)
		{
			if ($commons1[$i] != $commons2[$i]) $transpositions++;
		}
		$transpositions /= 2.0;

		// return the Jaro distance
		return ($commons1_len / ($str1_len) + $commons2_len / ($str2_len) + ($commons1_len - $transpositions) / ($commons1_len)) / 3.0;

	}

	private function getPrefixLength($string1, $string2, $MINPREFIXLENGTH = 4)
	{

		$n = min(array($MINPREFIXLENGTH, mb_strlen($string1), mb_strlen($string2)));

		for ($i = 0; $i < $n; $i++)
		{
			if ($string1[$i] != $string2[$i])
			{
				// return index of first occurrence of different characters
				return $i;
			}
		}

		// first n characters are the same
		return $n;
	}

	private function getCommonCharacters($string1, $string2, $allowedDistance)
	{

		$str1_len     = mb_strlen($string1);
		$str2_len     = mb_strlen($string2);
		$temp_string2 = $string2;

		$commonCharacters = '';
		for ($i = 0; $i < $str1_len; $i++)
		{

			$noMatch = true;
			// compare if char does match inside given allowedDistance
			// and if it does add it to commonCharacters
			for ($j = max(0, $i - $allowedDistance); $noMatch && $j < min($i + $allowedDistance + 1, $str2_len); $j++)
			{
				if ($temp_string2[$j] == $string1[$i])
				{
					$noMatch          = false;
					$commonCharacters .= $string1[$i];
					$temp_string2[$j] = '';
				}
			}
		}

		return $commonCharacters;
	}
}

class StringCompareJaroWinkler
{
	public function compare($str1, $str2)
	{
		return $this->JaroWinkler($str1, $str2, $PREFIXSCALE = 0.1);
	}

	private function getCommonCharacters($string1, $string2, $allowedDistance)
	{

		$str1_len     = mb_strlen($string1);
		$str2_len     = mb_strlen($string2);
		$temp_string2 = $string2;

		$commonCharacters = '';
		for ($i = 0; $i < $str1_len; $i++)
		{

			$noMatch = true;
			// compare if char does match inside given allowedDistance
			// and if it does add it to commonCharacters
			for ($j = max(0, $i - $allowedDistance); $noMatch && $j < min($i + $allowedDistance + 1, $str2_len); $j++)
			{
				if ($temp_string2[$j] == $string1[$i])
				{
					$noMatch          = false;
					$commonCharacters .= $string1[$i];
					$temp_string2[$j] = '';
				}
			}
		}

		return $commonCharacters;
	}

	private function Jaro($string1, $string2)
	{

		$str1_len = mb_strlen($string1);
		$str2_len = mb_strlen($string2);

		// theoretical distance
		$distance = (int) floor(min($str1_len, $str2_len) / 2.0);

		// get common characters
		$commons1 = $this->getCommonCharacters($string1, $string2, $distance);
		$commons2 = $this->getCommonCharacters($string2, $string1, $distance);

		if (($commons1_len = mb_strlen($commons1)) == 0) return 0;
		if (($commons2_len = mb_strlen($commons2)) == 0) return 0;
		// calculate transpositions
		$transpositions = 0;
		$upperBound     = min($commons1_len, $commons2_len);
		for ($i = 0; $i < $upperBound; $i++)
		{
			if ($commons1[$i] != $commons2[$i]) $transpositions++;
		}
		$transpositions /= 2.0;

		// return the Jaro distance
		return ($commons1_len / ($str1_len) + $commons2_len / ($str2_len) + ($commons1_len - $transpositions) / ($commons1_len)) / 3.0;

	}

	private function getPrefixLength($string1, $string2, $MINPREFIXLENGTH = 4)
	{

		$n = min(array($MINPREFIXLENGTH, mb_strlen($string1), mb_strlen($string2)));

		for ($i = 0; $i < $n; $i++)
		{
			if ($string1[$i] != $string2[$i])
			{
				// return index of first occurrence of different characters
				return $i;
			}
		}

		// first n characters are the same
		return $n;
	}

	private function JaroWinkler($string1, $string2, $PREFIXSCALE = 0.1)
	{

		$JaroDistance = $this->Jaro($string1, $string2);
		$prefixLength = $this->getPrefixLength($string1, $string2);

		return $JaroDistance + $prefixLength * $PREFIXSCALE * (1.0 - $JaroDistance);
	}
}

//class SmithWatermanGotoh
//{
//	private $gapValue;
//	private $substitution;
//
//	/**
//	 * Constructs a new Smith Waterman metric.
//	 *
//	 * @param   gapValue
//	 *            a non-positive gap penalty
//	 * @param   substitution
//	 *            a substitution function
//	 */
//	public function __construct($gapValue = -0.5,
//	                            $substitution = null)
//	{
//		if ($gapValue > 0.0) throw new Exception("gapValue must be <= 0");
//		if (empty($substitution)) $this->substitution = new SmithWatermanMatchMismatch(1.0, -2.0);
//		else $this->substitution = $substitution;
//		$this->gapValue = $gapValue;
//	}
//
//	public function compare($a, $b)
//	{
//		if (empty($a) && empty($b))
//		{
//			return 1.0;
//		}
//
//		if (empty($a) || empty($b))
//		{
//			return 0.0;
//		}
//
//		$maxDistance = min(mb_strlen($a), mb_strlen($b))
//			* max($this->substitution->max(), $this->gapValue);
//
//		return $this->smithWatermanGotoh($a, $b) / $maxDistance;
//	}
//
//	private function smithWatermanGotoh($s, $t)
//	{
//		$v0    = [];
//		$v1    = [];
//		$t_len = mb_strlen($t);
//		$max   = $v0[0] = max(0, $this->gapValue, $this->substitution->compare($s, 0, $t, 0));
//
//		for ($j = 1; $j < $t_len; $j++)
//		{
//			$v0[$j] = max(0, $v0[$j - 1] + $this->gapValue,
//				$this->substitution->compare($s, 0, $t, $j));
//
//			$max = max($max, $v0[$j]);
//		}
//
//		// Find max
//		for ($i = 1; $i < mb_strlen($s); $i++)
//		{
//			$v1[0] = max(0, $v0[0] + $this->gapValue, $this->substitution->compare($s, $i, $t, 0));
//
//			$max = max($max, $v1[0]);
//
//			for ($j = 1; $j < $t_len; $j++)
//			{
//				$v1[$j] = max(0, $v0[$j] + $this->gapValue, $v1[$j - 1] + $this->gapValue,
//					$v0[$j - 1] + $this->substitution->compare($s, $i, $t, $j));
//
//				$max = max($max, $v1[$j]);
//			}
//
//			for ($j = 0; $j < $t_len; $j++)
//			{
//				$v0[$j] = $v1[$j];
//			}
//		}
//
//		return $max;
//	}
//}
//
//class SmithWatermanMatchMismatch
//{
//	private $matchValue;
//	private $mismatchValue;
//
//	/**
//	 * Constructs a new match-mismatch substitution function. When two
//	 * characters are equal a score of <code>matchValue</code> is assigned. In
//	 * case of a mismatch a score of <code>mismatchValue</code>. The
//	 * <code>matchValue</code> must be strictly greater then
//	 * <code>mismatchValue</code>
//	 *
//	 * @param   matchValue
//	 *            value when characters are equal
//	 * @param   mismatchValue
//	 *            value when characters are not equal
//	 */
//	public function __construct($matchValue, $mismatchValue)
//	{
//		if ($matchValue <= $mismatchValue) throw new Exception("matchValue must be > matchValue");
//
//		$this->matchValue    = $matchValue;
//		$this->mismatchValue = $mismatchValue;
//	}
//
//	public function compare($a, $aIndex, $b, $bIndex)
//	{
//		return ($a[$aIndex] === $b[$bIndex] ? $this->matchValue
//			: $this->mismatchValue);
//	}
//
//	public function max()
//	{
//		return $this->matchValue;
//	}
//
//	public function min()
//	{
//		return $this->mismatchValue;
//	}
//}

//$str1 = "COELACANTH";
//$str2 = "PELICAN";
//$o = new SmithWatermanGotoh();
//echo $o->compare($str1, $str2);