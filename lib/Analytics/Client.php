<?php
namespace SegmentIO;

require(__DIR__ . '/Consumer.php');
require(__DIR__ . '/QueueConsumer.php');
require(__DIR__ . '/Consumer/File.php');
require(__DIR__ . '/Consumer/ForkCurl.php');
require(__DIR__ . '/Consumer/Socket.php');
require(__DIR__ . '/Consumer/Fornax.php');


class Analytics_Client {

  private $consumer;

  /**
   * Create a new analytics object with your app's secret
   * key
   *
   * @param string $secret
   * @param array  $options array of consumer options [optional]
   * @param string Consumer constructor to use, socket by default.
   */
  public function __construct($secret, $options = array()) {

    $consumers = array(
      "socket"     => "SegmentIO\\Analytics_Consumer_Socket",
      "file"       => "SegmentIO\\Analytics_Consumer_File",
      "fork_curl"  => "SegmentIO\\Analytics_Consumer_ForkCurl",
      "fornax"     => "SegmentIO\\Analytics_Consumer_Fornax"
    );

    # Use our socket consumer by default
    $consumer_types = isset($options["consumer"]) ? $options["consumer"] :
                                                   "socket";
    # support multiple consumers
    $this->consumer = array();

    if (is_array($consumer_types)) {
      foreach ($consumer_types as $consumer_type) {
        $Consumer = $consumers[$consumer_type];
        $this->consumer[] = new $Consumer($secret, $options);
      }
    } else {
      $Consumer = $consumers[$consumer_types];
      $this->consumer[] = new $Consumer($secret, $options);
    }
  }

  public function __destruct() {
    if (is_array($this->consumer)) {
      foreach ($this->consumer as $consumer) {
        $consumer->__destruct();
      }
      return;
    }
    $this->consumer->__destruct();
  }

  /**
   * Tracks a user action
   * @param  [string] $user_id    user id string
   * @param  [string] $event      name of the event
   * @param  [array]  $properties properties associated with the event [optional]
   * @param  [number] $timestamp  unix seconds since epoch (time()) [optional]
   * @return [boolean] whether the track call succeeded
   */
  public function track($user_id, $event, $properties = null,
                        $timestamp = null, $context = array()) {

    $context = array_merge($context, $this->getContext());

    $timestamp = $this->formatTime($timestamp);

    // json_encode will serialize as []
    if (count($properties) == 0) {
      $properties = null;
    }

    $returnValue = array_map(function($consumer) use ($user_id, $event, $properties, $context, $timestamp) {
      return $consumer->track(
        $user_id,
        $event,
        $properties,
        $context,
        $timestamp
      );
    }, $this->consumer);

    return array_combine($this->getConsumerNames(), $returnValue);
  }

  /**
   * Tags traits about the user.
   * @param  [string] $user_id
   * @param  [array]  $traits
   * @param  [number] $timestamp  unix seconds since epoch (time()) [optional]
   * @return [boolean] whether the track call succeeded
   */
  public function identify($user_id, $traits = array(), $timestamp = null,
                            $context = array()) {

    $context = array_merge($context, $this->getContext());

    $timestamp = $this->formatTime($timestamp);

    // json_encode will serialize as []
    if (count($traits) == 0) {
      $traits = null;
    }

    $returnValue = array_map(function($consumer) use ($user_id, $traits, $context, $timestamp) {
      return $consumer->identify(
        $user_id,
        $traits,
        $context,
        $timestamp
      );
    }, $this->consumer);


    return array_combine($this->getConsumerNames(), $returnValue);
  }

  public function getConsumerNames()
  {
    return array_map(
      function($consumer) {
        return get_class($consumer);
      },
      $this->consumer
    );
  }

  /**
   * Aliases from one user id to another
   * @param  string $from
   * @param  string $to
   * @param  number $timestamp unix seconds since epoch (time()) [optional]
   * @param  array  $context   [optional]
   * @return boolean whether the alias call succeeded
   */
  public function alias($from, $to, $timestamp = null, $context = array()) {

    $context = array_merge($context, $this->getContext());

    $timestamp = $this->formatTime($timestamp);

    return $this->consumer->alias($from, $to, $context, $timestamp);
  }

  /**
   * Formats a timestamp by making sure it is set, and then converting it to
   * iso8601 format.
   * @param  time $timestamp - time in seconds (time())
   */
  private function formatTime($timestamp) {

    if ($timestamp == null) $timestamp = time();

    # Format for iso8601
    return date("c", $timestamp);
  }


  /**
   * Add the segment.io context to the request
   * @return array additional context
   */
  private function getContext () {
    return array( "library" => "analytics-php" );
  }
}
