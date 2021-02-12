<?php

abstract class CRM_MembershipExtras_Queue_Task_Base {

  /**
   * @param \CRM_Queue_TaskContext $ctx
   * @param $records
   *
   * @return bool
   */
  public static function run(CRM_Queue_TaskContext $ctx, $records) {
    $processingStartTime = microtime(TRUE);

    foreach ($records as $record) {
      try {
        $task = new static();
        $task->process($record);
      }
      catch (Exception $e) {
        $errorMessage = 'Failed to process task ' . static::class . ' with Id: ' . $record . ' - Error message : ' . $e->getMessage();
        $ctx->log->err($errorMessage);
      }
    }

    $totalExecutionTime = (microtime(TRUE) - $processingStartTime);
    $endProcessingMessage = 'Finished processing the ' . static::class . ' task In : ' . $totalExecutionTime . ' Seconds';
    $ctx->log->info($endProcessingMessage);

    return TRUE;
  }

  abstract protected function process($record);

}
