<?php

abstract class CRM_MembershipExtras_Queue_Builder_Base {

  public const RECORDS_LIMIT = 10;

  protected $queue;

  protected $records = [];

  protected $taskCallback = [];

  /**
   * CRM_MembershipExtras_Queue_Build_BaseBuilder constructor.
   *
   * @param CRM_Queue_Queue $queue
   */
  public function __construct($queue) {
    $this->queue = $queue;
  }

  protected function buildQueue($records) {
    foreach ($records as $record) {
      if (count($this->records) >= self::RECORDS_LIMIT) {
        $this->addQueueTaskItem();
        $this->records = [];
      }

      $this->records[] = $record;
    }

    if (!empty($this->records)) {
      $this->addQueueTaskItem();
    }
  }

  protected function addQueueTaskItem() {
    $records = implode(', ', $this->records);
    $taskTitle = sprintf('Processing the records: %s', $records);

    $task = new CRM_Queue_Task(
      $this->taskCallback,
      [$this->records],
      $taskTitle
    );

    $this->queue->createItem($task);
  }

}
