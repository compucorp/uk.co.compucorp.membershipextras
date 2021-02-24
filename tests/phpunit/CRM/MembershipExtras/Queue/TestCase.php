<?php

/**
 * Abstract CRM_MembershipExtras_Queue_TestCase
 *
 * @group headless
 */
abstract class CRM_MembershipExtras_Queue_TestCase extends BaseHeadlessTest {

  /**
   * @var \CRM_Queue_Service
   */
  private $queueService;

  /**
   * @var \CRM_Queue_Queue
   */
  private $queue;

  /**
   * @var \CRM_Queue_Runner
   */
  private $runner;

  public function setUp() {
    $this->setUpQueue();
  }

  public function tearDown() {
    $this->tearDownQueue();
  }

  /**
   * Create in-memory queue
   */
  private function setUpQueue() {
    $this->queueService = CRM_Queue_Service::singleton(TRUE);
    $this->queue = $this->queueService->create([
      'type' => 'Memory',
      'name' => 'uk.co.compucorp.membershipextras.test.queue.offlineautorenewal',
    ]);
    $this->runner = new CRM_Queue_Runner([
      'queue' => $this->queue,
      'errorMode' => CRM_Queue_Runner::ERROR_ABORT,
    ]);
  }

  /**
   * Delete the queue
   */
  private function tearDownQueue() {
    unset($this->queue);
    unset($this->queueService);
    unset($this->runner);
  }

  /**
   * Run the specified queue builder to add tasks to the queue
   */
  protected function runQueueBuilder($queueBuilderClass) {
    $queueBuilder = new $queueBuilderClass($this->queue);
    $queueBuilder->run();
  }

  /**
   * Run all the tasks
   */
  protected function runQueueRunner() {
    $this->runner->runAll();
  }

  /**
   * Get the number of Tasks
   *
   * @return int
   */
  protected function getNumberOfTasks() {
    return $this->queue->numberOfItems();
  }

}
