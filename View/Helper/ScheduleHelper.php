<?php
App::uses('AppHelper', 'View/Helper');

/**
 * Schedule helper
 *
 */
class ScheduleHelper extends AppHelper {

/**
 * Time format (as used in `date`)
 *
 * @var string
 */
	protected $_timeFormat = 'g:i a';

/**
 * Default Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {

		if ( array_key_exists('timeFormat',$settings) )
			$this->_timeFormat = $settings['timeFormat'];
		else if ( $tmp = Configure::read('BostonConference.timeFormat') )
			$this->_timeFormat = $tmp;

		parent::__construct($View, $settings);
	}

/**
 * Gets the classes fir a talk element.
 *
 * @param array $talk The talk associative array as returned by the Talk model.
 * @param array $talks An array of all talks.
 * @param int $i The index of the talk in the current time block.
 * @return string A class string for the talk div.
 */
	public function getTalkClass( $talk, $talks, $i )
	{
		$duration = floor($talk['Talk']['duration']/15);
		$class = '';

		if ( $duration == 0 || $duration == 1 )
			$class = 'minutes-15';
		else if ( $duration == 2 )
			$class = 'minutes-30';
		else if ( $duration == 3 )
			$class = 'minutes-45';
		else
			$class = 'minutes-60';

		if ( $talks > 1 )
			$class .= ' ';

		if ( $talks == 2 )
			$class .= 'double-block';
		else if ( $talks == 3 )
			$class .= 'tripple-block';
		else if ( $talks == 4 )
			$class .= 'quad-block';

		if ( $i > 0 )
			$class .= ' talk-'.($i+1);

		if ( !empty($talk['Track']['id']) )
			$class .= ' track-'.$talk['Track']['id'];

		return $class;
	}
	
/**
 * Gets all the talks in a particular time block.
 *
 * @param int $block The current time block to get talks in.
 * @param array $talks An array of all talks.
 * @param int $startIndex The index to start traversing the talk array from.
 * @return array An array of talks.
 */
	public function getTalksInBlock( $block, $talks, $startIndex=0 )
	{
		$talkBlock = array();
		$c = count($talks);

		for ( $i = $startIndex; $i < $c; $i++ ) {
			$nextTalk = $talks[$i];

			$tmp = strtotime($nextTalk['Talk']['start_time']);
			if ( $tmp-(date('i',$tmp)%30*60) == $block ) {
				$talkBlock[] = $nextTalk;
			}
			else {
				break;
			}
		}

		return $talkBlock;
	}

/**
 * Gets the HTML for a conference calandar.
 *
 * @param int $block The current time block to get talks in.
 * @param array $talks An array of all talks.
 * @param int $startIndex The index to start traversing the talk array from.
 * @return string The HTML for the calandar.
 */
	public function calandar( $talks )
	{
		$c = count($talks);

		$output = '';
	
		$output .= '<div class="schedule">';

		// Stores the current day for day headers
		$day = null;
		$dayIndex = 0;

		// Stores the block state for time display
		$previousBlock = 0;
		$blockEnd = 0;

		// Stores the status of multi-block talks
		$blockMap = array( 0, 0, 0, 0 );
		$colCount = 0;

		// Loop through all the blocks (note: $c is set in the previous `if`)
		for( $i = 0; $i < $c; $i++ )
		{
			$talk = $talks[$i];

			$startTime = strtotime($talk['Talk']['start_time']);

			// Echo the day header if appropriate
			if ( date('z',$startTime) != $day ) {
				$day = date('z',$startTime);

				if ( $previousBlock != 0 ) {
					for ( $b = $previousBlock + 30*60; $b < $blockEnd; $b += 30*60 ) {
						$output .= '<div class="block">';
						$output .= '<div class="time"><p>'.date($this->_timeFormat,$b).'</p></div>';
						$output .= '</div>';
					}
					$previousBlock = 0;
					$colCount = 0;
					foreach( $blockMap as $key => $val ) $blockMap[$key] = 0;
				}

				$output .= '<div class="day">Day '.(++$dayIndex).' - '.date('l, F jS, Y',$startTime).'</div>';
			}

			// Calulate the current block
			$block = $startTime - (date('i',$startTime)%30*60);
			$blockEnd = $block + $talk['Talk']['duration']*60;

			// Fill in "empty" time blocks if appropriate
			if ( $previousBlock != 0 ) {
				$emptyBlocks = 1;	

				for ( $b = $previousBlock + 30*60; $b < $block; $b += 30*60 ) {
					$output .= '<div class="block">';
					$output .= '<div class="time"><p>'.date($this->_timeFormat,$b).'</p></div>';
					$output .= '</div>';
					$emptyBlocks++;
				}

				$resetColCount = true;

				foreach ( $blockMap as $bi => $blockHeight ) {
					if ( $blockHeight > $emptyBlocks ) {
						$blockMap[$bi] -= $emptyBlocks;
						if ( $blockMap[$bi] != 0 )
							$resetColCount = false;
					} else {
						$blockMap[$bi] = 0;
					}
				}

				if ( $resetColCount )
					$colCount = 0;
			}
			$previousBlock = $block;

			// Start the current time block
			$output .= '<div class="block">';


			// Create an array of talks in this time block
			$talkBlock = $this->getTalksInBlock($block,$talks,$i);

			if ( count($talkBlock) > 1 )
			{
				$workingDuration = $talk['Talk']['duration'];
				foreach( $talkBlock as $tmpTalk )
				{
					if ( $tmpTalk['Talk']['duration'] > $workingDuration ) {
						$workingDuration = $tmpTalk['Talk']['duration'];
						$blockEnd = $block + $workingDuration*60;
					}
				}

				$i += count($talkBlock)-1;
			}

			// Set the column count if we're not still using the count from
			// a previous block
			// This is currently not recursive so it only produces accurate
			// results if the next blocks do not require additional empty
			// columns
			if ( $colCount == 0 )
			{
				$colCount = count($talkBlock);
				$j = 1;

				for ( $b = $block+30*60; $b < $blockEnd; $b += 30*60 ) {
					$blockCount = count( $this->getTalksInBlock($b,$talks,$i+$j) );
					$j += $blockCount;

					if ( $blockCount >= $colCount )
						$colCount = $blockCount+1;
				}
			}

			// Echo the time for this block
			$output .= '<div class="time"><p>'.date($this->_timeFormat,$block).'</p></div>';

			$col = 0;
			foreach ( $talkBlock as $ii => $talk ) {
				if ( $blockMap[$col] > 0 )
					$col++;

				$output .= '<div class="talk '.$this->getTalkClass($talk,$colCount, $col).'"><p>'.$talk['Talk']['topic'];

				if ( !empty($talk['Speaker']['display_name']) )
					$output .= '<span> -&nbsp;'.$talk['Speaker']['display_name'].'</span>';


				$output .= '</p></div>';
				$blockMap[$col] += ceil($talk['Talk']['duration']/30);

				$col++;
			}

			$output .= '</div>';
		}

		if ( $previousBlock != 0 ) {
			for ( $b = $previousBlock + 30*60; $b < $blockEnd; $b += 30*60 ) {
				$output .= '<div class="block">';
				$output .= '<div class="time"><p>'.date($this->_timeFormat,$b).'</p></div>';
				$output .= '</div>';
			}
		}


		$output .= '</div>';

		return $output;
	}
}
