<?php

/**
 * Class CRM_MembershipExtras_Hook_Alter_MailParamsHandler.
 *
 * Implements alterMailParams hook.
 */
class CRM_MembershipExtras_Hook_Alter_MailParamsHandler {

  /**
   * Parameters being passed to template.
   *
   * @var array
   */
  private $params = [];

  /**
   * Static cache for contribution data to prevent N+1 queries
   * @var array
   */
  private static $contributionCache = [];
  
  /**
   * LRU order tracking for contribution cache
   * @var array
   */
  private static $contributionCacheOrder = [];

  /**
   * Track processing times for batch detection
   * @var array
   */
  private static $batchProcessingIds = [];
  
  /**
   * Maximum cache size for LRU eviction
   * @var int
   */
  private static $maxCacheSize = 200;

  public function __construct(&$params) {
    $this->params =& $params;
  }

  /**
   * Alters the parameters for the e-mail.
   */
  public function handle() {
    $this->useReceiveDateAsInvoiceDate();
    $this->performMemoryManagement();
  }

  /**
   * Changes the invoice date to be the contribution's receive date.
   */
  public function useReceiveDateAsInvoiceDate() {
    if (empty($this->params['valueName']) || $this->params['valueName'] != 'contribution_invoice_receipt') {
      return;
    }

    $contributionId = $this->params['tplParams']['id'];
    
    // Use LRU cached contribution data to prevent N+1 queries
    $contribution = $this->getContributionFromLRUCache($contributionId);
    if (!$contribution) {
      try {
        $contribution = civicrm_api3('Contribution', 'getsingle', [
          'id' => $contributionId,
        ]);
        
        // Add to LRU cache
        $this->addToLRUCache($contributionId, $contribution);
        
        // Track for batch processing detection
        self::$batchProcessingIds[] = $contributionId;
      } catch (Exception $e) {
        \Civi::log()->error('Failed to fetch contribution ' . $contributionId . ': ' . $e->getMessage());
        return;
      }
    }
    $this->params['tplParams']['invoice_date'] = date('F j, Y', strtotime($contribution['receive_date']));
  }

  /**
   * Performs memory management operations during bulk processing.
   */
  private function performMemoryManagement() {
    $batchCount = count(self::$batchProcessingIds);
    
    // Clean up batch tracking array to prevent unlimited growth
    if ($batchCount > 100) {
      // Keep only the most recent 50 batch IDs
      self::$batchProcessingIds = array_slice(self::$batchProcessingIds, -50);
      \Civi::log()->debug('MailParamsHandler: Batch tracking trimmed, LRU cache size: ' . count(self::$contributionCache));
    }
    
    // Adaptive GC using iteration-count trigger for membership processing
    CRM_MembershipExtras_Common_GCManager::maybeCollectGarbage('membership_processing');
  }
  
  /**
   * Gets contribution from LRU cache.
   */
  private function getContributionFromLRUCache($contributionId) {
    if (isset(self::$contributionCache[$contributionId])) {
      // Move to end of LRU order (most recently used)
      $this->updateLRUOrder($contributionId);
      return self::$contributionCache[$contributionId];
    }
    return FALSE;
  }
  
  /**
   * Adds contribution to LRU cache, evicting least recently used if at capacity.
   */
  private function addToLRUCache($contributionId, $contribution) {
    // If already exists, update value and move to end
    if (isset(self::$contributionCache[$contributionId])) {
      self::$contributionCache[$contributionId] = $contribution;
      $this->updateLRUOrder($contributionId);
      return;
    }
    
    // If at capacity, remove least recently used item
    if (count(self::$contributionCache) >= self::$maxCacheSize) {
      $lruKey = array_shift(self::$contributionCacheOrder);
      unset(self::$contributionCache[$lruKey]);
    }
    
    // Add new item
    self::$contributionCache[$contributionId] = $contribution;
    self::$contributionCacheOrder[] = $contributionId;
  }
  
  /**
   * Updates LRU order by moving item to end (most recently used).
   */
  private function updateLRUOrder($contributionId) {
    $index = array_search($contributionId, self::$contributionCacheOrder);
    if ($index !== FALSE) {
      unset(self::$contributionCacheOrder[$index]);
      self::$contributionCacheOrder = array_values(self::$contributionCacheOrder); // Re-index
    }
    self::$contributionCacheOrder[] = $contributionId;
  }

}
