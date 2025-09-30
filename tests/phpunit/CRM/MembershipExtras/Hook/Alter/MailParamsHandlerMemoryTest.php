<?php

require_once __DIR__ . '/../../../../../../CRM/MembershipExtras/Hook/Alter/MailParamsHandler.php';

/**
 * Test memory optimization features in MailParamsHandler class.
 *
 * @group memory
 * @group membershipextras
 */
class CRM_MembershipExtras_Hook_Alter_MailParamsHandlerMemoryTest extends \PHPUnit\Framework\TestCase {

  public function setUp(): void {
    parent::setUp();
    
    // Reset static caches before each test
    $reflection = new \ReflectionClass('CRM_MembershipExtras_Hook_Alter_MailParamsHandler');
    
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cacheProperty->setValue(NULL, []);
    
    $cacheOrderProperty = $reflection->getProperty('contributionCacheOrder');
    $cacheOrderProperty->setAccessible(TRUE);
    $cacheOrderProperty->setValue(NULL, []);
    
    $batchProperty = $reflection->getProperty('batchProcessingIds');
    $batchProperty->setAccessible(TRUE);
    $batchProperty->setValue(NULL, []);
    
    // Mock civicrm_api3 function if not exists
    if (!function_exists('civicrm_api3')) {
      $this->mockCivicrmApi3();
    }
    
    // Mock Civi class for logging
    if (!class_exists('\Civi')) {
      $this->createCiviMock();
    }
  }

  /**
   * Test contribution caching prevents N+1 queries.
   */
  public function testContributionCaching(): void {
    $params = [
      'valueName' => 'contribution_invoice_receipt',
      'tplParams' => ['id' => 123]
    ];
    
    $handler1 = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $handler2 = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    
    // Mock API call counter
    global $apiCallCount;
    $apiCallCount = 0;
    
    // First call should hit the API
    $handler1->handle();
    $this->assertEquals(1, $apiCallCount, 'First call should hit the API');
    
    // Second call should use cache
    $handler2->handle();
    $this->assertEquals(1, $apiCallCount, 'Second call should use cached data');
    
    // Verify cache contains the contribution
    $reflection = new \ReflectionClass('CRM_MembershipExtras_Hook_Alter_MailParamsHandler');
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cache = $cacheProperty->getValue();
    
    $this->assertArrayHasKey(123, $cache, 'Cache should contain contribution ID 123');
  }

  /**
   * Test batch processing ID tracking.
   */
  public function testBatchProcessingTracking(): void {
    $contributionIds = [101, 102, 103, 104, 105];
    
    foreach ($contributionIds as $id) {
      $params = [
        'valueName' => 'contribution_invoice_receipt',
        'tplParams' => ['id' => $id]
      ];
      
      $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
      $handler->handle();
    }
    
    // Check batch processing IDs are tracked
    $reflection = new \ReflectionClass('CRM_MembershipExtras_Hook_Alter_MailParamsHandler');
    $batchProperty = $reflection->getProperty('batchProcessingIds');
    $batchProperty->setAccessible(TRUE);
    $batchIds = $batchProperty->getValue();
    
    $this->assertEquals($contributionIds, $batchIds, 'Batch processing IDs should be tracked');
  }

  /**
   * Test cache trimming during bulk operations.
   */
  public function testCacheTrimming(): void {
    // Process 150 contributions to trigger cache trimming (> 100)
    for ($i = 1; $i <= 150; $i++) {
      $params = [
        'valueName' => 'contribution_invoice_receipt',
        'tplParams' => ['id' => $i]
      ];
      
      $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
      $handler->handle();
    }
    
    // Check cache has been trimmed
    $reflection = new \ReflectionClass('CRM_MembershipExtras_Hook_Alter_MailParamsHandler');
    
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cache = $cacheProperty->getValue();
    
    $batchProperty = $reflection->getProperty('batchProcessingIds');
    $batchProperty->setAccessible(TRUE);
    $batchIds = $batchProperty->getValue();
    
    // Cache should be trimmed to 50 most recent entries
    $this->assertLessThanOrEqual(50, count($cache), 'Cache should be trimmed to 50 entries');
    $this->assertLessThanOrEqual(50, count($batchIds), 'Batch IDs should be trimmed to 50 entries');
    
    // Should contain the most recent IDs (101-150)
    $expectedIds = range(101, 150);
    $this->assertEquals($expectedIds, $batchIds, 'Should keep most recent batch IDs');
  }

  /**
   * Test memory usage during bulk processing.
   */
  public function testBulkProcessingMemoryUsage(): void {
    $startMemory = memory_get_usage(TRUE);
    
    // Process 200 contributions (should trigger cache trimming twice)
    for ($i = 1; $i <= 200; $i++) {
      $params = [
        'valueName' => 'contribution_invoice_receipt',
        'tplParams' => ['id' => $i]
      ];
      
      $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
      $handler->handle();
    }
    
    $endMemory = memory_get_usage(TRUE);
    $memoryIncrease = ($endMemory - $startMemory) / (1024 * 1024); // MB
    
    // Memory increase should be reasonable (under 20MB for 200 contributions)
    $this->assertLessThan(20, $memoryIncrease, 
      'Memory usage should remain bounded with cache trimming');
  }

  /**
   * Test error handling in API calls.
   */
  public function testApiErrorHandling(): void {
    // Mock API to throw exception
    global $shouldThrowException;
    $shouldThrowException = TRUE;
    
    $params = [
      'valueName' => 'contribution_invoice_receipt',
      'tplParams' => ['id' => 999]
    ];
    
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    
    // Should not throw exception (error should be caught and logged)
    $handler->handle();
    
    // Verify no invoice_date was set due to error
    $this->assertArrayNotHasKey('invoice_date', $params['tplParams'], 
      'invoice_date should not be set when API call fails');
    
    $shouldThrowException = FALSE;
  }

  /**
   * Test non-invoice emails are ignored.
   */
  public function testNonInvoiceEmailsIgnored(): void {
    $params = [
      'valueName' => 'contribution_receipt', // Not invoice
      'tplParams' => ['id' => 123]
    ];
    
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $handler->handle();
    
    // Check cache remains empty
    $reflection = new \ReflectionClass('CRM_MembershipExtras_Hook_Alter_MailParamsHandler');
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cache = $cacheProperty->getValue();
    
    $this->assertEmpty($cache, 'Cache should remain empty for non-invoice emails');
  }

  /**
   * Test invoice date formatting.
   */
  public function testInvoiceDateFormatting(): void {
    $params = [
      'valueName' => 'contribution_invoice_receipt',
      'tplParams' => ['id' => 123]
    ];
    
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $handler->handle();
    
    // Check invoice_date was set with correct format
    $this->assertArrayHasKey('invoice_date', $params['tplParams'], 
      'invoice_date should be set');
    
    $expectedDate = date('F j, Y', strtotime('2023-01-15 10:00:00'));
    $this->assertEquals($expectedDate, $params['tplParams']['invoice_date'], 
      'Invoice date should be properly formatted');
  }

  /**
   * Mock civicrm_api3 function for testing.
   */
  private function mockCivicrmApi3(): void {
    eval('
      function civicrm_api3($entity, $action, $params = []) {
        global $apiCallCount, $shouldThrowException;
        
        if (!isset($apiCallCount)) {
          $apiCallCount = 0;
        }
        $apiCallCount++;
        
        if (isset($shouldThrowException) && $shouldThrowException) {
          throw new Exception("API Error for testing");
        }
        
        if ($entity === "Contribution" && $action === "getsingle") {
          return [
            "id" => $params["id"],
            "receive_date" => "2023-01-15 10:00:00"
          ];
        }
        
        return [];
      }
    ');
  }

  /**
   * Test LRU cache functionality.
   */
  public function testLRUCacheFunctionality(): void {
    $params = ['tplParams' => ['id' => 123]];
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $reflection = new \ReflectionClass($handler);
    
    // Test LRU cache methods
    $getFromCacheMethod = $reflection->getMethod('getContributionFromLRUCache');
    $getFromCacheMethod->setAccessible(TRUE);
    $addToCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToCacheMethod->setAccessible(TRUE);
    $updateOrderMethod = $reflection->getMethod('updateLRUOrder');
    $updateOrderMethod->setAccessible(TRUE);
    
    // Test cache miss
    $result = $getFromCacheMethod->invoke($handler, 999);
    $this->assertFalse($result);
    
    // Add to cache
    $testData = ['id' => 123, 'receive_date' => '2024-01-15'];
    $addToCacheMethod->invoke($handler, 123, $testData);
    
    // Test cache hit
    $result = $getFromCacheMethod->invoke($handler, 123);
    $this->assertEquals($testData, $result);
  }
  
  /**
   * Test LRU cache eviction when at capacity.
   */
  public function testLRUCacheEviction(): void {
    $params = ['tplParams' => ['id' => 1]];
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $reflection = new \ReflectionClass($handler);
    
    // Set small cache size
    $maxSizeProperty = $reflection->getProperty('maxCacheSize');
    $maxSizeProperty->setAccessible(TRUE);
    $maxSizeProperty->setValue(NULL, 3);
    
    $addToCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToCacheMethod->setAccessible(TRUE);
    
    // Fill cache to capacity
    for ($i = 1; $i <= 3; $i++) {
      $addToCacheMethod->invoke($handler, $i, ['id' => $i]);
    }
    
    // Check cache size
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cache = $cacheProperty->getValue();
    $this->assertCount(3, $cache);
    
    // Add one more - should evict LRU
    $addToCacheMethod->invoke($handler, 4, ['id' => 4]);
    $cache = $cacheProperty->getValue();
    
    $this->assertCount(3, $cache);
    $this->assertFalse(isset($cache[1])); // First item evicted
    $this->assertTrue(isset($cache[4])); // New item present
  }
  
  /**
   * Test LRU order updates.
   */
  public function testLRUOrderUpdates(): void {
    $params = ['tplParams' => ['id' => 1]];
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $reflection = new \ReflectionClass($handler);
    
    $updateOrderMethod = $reflection->getMethod('updateLRUOrder');
    $updateOrderMethod->setAccessible(TRUE);
    $orderProperty = $reflection->getProperty('contributionCacheOrder');
    $orderProperty->setAccessible(TRUE);
    
    // Set initial order
    $order = [1, 2, 3, 4];
    $orderProperty->setValue(NULL, $order);
    
    // Move item 2 to end
    $updateOrderMethod->invoke($handler, 2);
    $updatedOrder = $orderProperty->getValue();
    
    // Item 2 should now be at the end
    $this->assertEquals(2, end($updatedOrder));
    $this->assertCount(4, $updatedOrder);
  }
  
  /**
   * Test cache performance with repeated access.
   */
  public function testCachePerformanceWithRepeatedAccess(): void {
    $params = ['tplParams' => ['id' => 123]];
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $reflection = new \ReflectionClass($handler);
    
    $getFromCacheMethod = $reflection->getMethod('getContributionFromLRUCache');
    $getFromCacheMethod->setAccessible(TRUE);
    $addToCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToCacheMethod->setAccessible(TRUE);
    
    // Add test data
    $testData = ['id' => 123, 'receive_date' => '2024-01-15'];
    $addToCacheMethod->invoke($handler, 123, $testData);
    
    // Multiple accesses should work correctly
    for ($i = 0; $i < 10; $i++) {
      $result = $getFromCacheMethod->invoke($handler, 123);
      $this->assertEquals($testData, $result);
    }
  }
  
  /**
   * Test cache maintains proper size limits.
   */
  public function testCacheSizeLimits(): void {
    $params = ['tplParams' => ['id' => 1]];
    $handler = new CRM_MembershipExtras_Hook_Alter_MailParamsHandler($params);
    $reflection = new \ReflectionClass($handler);
    
    // Set reasonable cache size
    $maxSizeProperty = $reflection->getProperty('maxCacheSize');
    $maxSizeProperty->setAccessible(TRUE);
    $maxSize = $maxSizeProperty->getValue();
    
    $addToCacheMethod = $reflection->getMethod('addToLRUCache');
    $addToCacheMethod->setAccessible(TRUE);
    
    // Add more items than max size
    for ($i = 1; $i <= $maxSize + 10; $i++) {
      $addToCacheMethod->invoke($handler, $i, ['id' => $i]);
    }
    
    // Cache should never exceed max size
    $cacheProperty = $reflection->getProperty('contributionCache');
    $cacheProperty->setAccessible(TRUE);
    $cache = $cacheProperty->getValue();
    
    $this->assertLessThanOrEqual($maxSize, count($cache));
  }

  /**
   * Create Civi mock for logging.
   */
  private function createCiviMock(): void {
    if (!class_exists('\Civi')) {
      eval('
        class Civi {
          public static function log() {
            return new class {
              public function error($message) {
                // Mock logger
              }
              public function debug($message) {
                // Mock logger  
              }
            };
          }
        }
      ');
    }
  }

}