<?php

namespace SegmentIO;

class Analytics_Consumer_Fornax extends Analytics_Consumer {
  private $file_handle;
  protected $type = "Fornax";
  const LOG_TTL = 300; // 5 minutes

  public function getFilename()
  {
    $filename = getmypid() . '_' . (floor(time() / self::LOG_TTL) * self::LOG_TTL) . '.ldjson';
    return $this->options['fornax_base_path'] . $filename;
  }

  /**
   * The file consumer writes track and identify calls to a file.
   * @param string $secret
   * @param array  $options
   *     string "filename" - where to log the analytics calls
   */
  public function __construct($secret, $options = array()) {
    // default options
    $options = array_merge(array(
      'filepermissions' => 0777
    ), $options);

    parent::__construct($secret, $options);

    $options['filename'] = $this->getFilename();

    try {
      $this->file_handle = fopen($options["filename"], "a");
      chmod($options["filename"], $options["filepermissions"]);
    } catch (\Exception $e) {
      $this->handleError($e->getCode(), $e->getMessage());
    }
  }

  public function __destruct() {
    if ($this->file_handle &&
        get_resource_type($this->file_handle) != "Unknown") {
      fclose($this->file_handle);
    }
  }

  /**
   * Tracks a user action
   * @param  [string] $user_id    user id string
   * @param  [string] $event      name of the event
   * @param  [array]  $properties properties associated with the event
   * @param  [string] $timestamp  iso8601 of the timestamp
   * @return [boolean] whether the track call succeeded
   */
  public function track($user_id, $event, $properties, $context, $timestamp) {
    // Fornax will drop events that do not contain a period
    if (!strpos($event, '.')) {
      // Append MissingDomain. prefix, so we can group and track these events that do not conform
      $event = 'MissingDomain.' . ltrim($event, '.');
    }

    if (!is_array($properties)) {
      // if a non-array was passed, create an array out of it
      if (!empty($properties)) {
        $properties = array(
          'message' => $properties
        );
      } else {
        $properties = array();
      }
    }

    if (isset($this->options['defaultProperties'])) {
      $properties = array_merge($properties, $this->options['defaultProperties']);
    }

    $body = array(
      "userId"    => $user_id,
      "event"      => $event,
      "properties" => $properties,
      "timestamp"  => $timestamp,
      "context"    => $context,
      "action"     => "track"
    );

    return $this->write($body);
  }

  /**
   * Tags traits about the user.
   * @param  [string] $user_id
   * @param  [array]  $traits
   * @param  [string] $timestamp   iso8601 of the timestamp
   * @return [boolean] whether the track call succeeded
   */
  public function identify($user_id, $traits, $context, $timestamp) {

    $body = array(
      "userId"    => $user_id,
      "traits"     => $traits,
      "context"    => $context,
      "timestamp"  => $timestamp,
      "action"     => "identify"
    );

    return $this->write($body);
  }

  /**
   * Aliases from one user id to another
   * @param  string $from
   * @param  string $to
   * @param  array  $context
   * @param  string $timestamp   iso8601 of the timestamp
   * @return boolean whether the alias call succeeded
   */
  public function alias($from, $to, $context, $timestamp) {

    $body = array(
      "from"       => $from,
      "to"         => $to,
      "context"    => $context,
      "timestamp"  => $timestamp,
      "action"     => "alias"
    );

    return $this->write($body);
  }

  /**
   * Writes the API call to a file as line-delimited json
   * @param  [array]   $body post body content.
   * @return [boolean] whether the request succeeded
   */
  private function write($body) {
    if (!$this->file_handle)
      return false;

    if (!empty($this->options['anonymousId'])) {
      $body['anonymousId'] = $this->options['anonymousId'];
    }

    if (isset($this->options['defaultContext']) && is_array($this->options['defaultContext'])) {
      $body['context'] = array_merge($this->options['defaultContext'], $body['context']);
    }

    $content = json_encode($body);
    $content.= "\n";

    return fwrite($this->file_handle, $content) == strlen($content);
  }
}
