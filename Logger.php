<?php
/**
 * Logger Simple - PHP Client
 * Version 1.0.0 - PHP Client for Logger Simple API
 * 
 * @author Logger Simple Team
 * @license MIT
 * @link https://github.com/logger-simple/logger-php
 */

class LoggerSimple
{
    private $appId;
    private $apiKey;
    private $apiUrl;
    private $options;
    private $metrics;
    private $isConnected;
    
    /**
     * @var callable[]
     */
    private $eventListeners = [];
    
    const VERSION = '1.0.0';
    const USER_AGENT = 'Logger-Simple-PHP/' . self::VERSION;
    
    /**
     * Constructor
     * 
     * @param array $config Logger configuration
     * @throws InvalidArgumentException
     */
    public function __construct(array $config)
    {
        if (empty($config['app_id']) || empty($config['api_key'])) {
            throw new InvalidArgumentException('app_id and api_key are required');
        }
        
        $this->appId = $config['app_id'];
        $this->apiKey = $config['api_key'];
        $this->apiUrl = $config['api_url'] ?? 'https://api.logger-simple.com/';
        
        // Default options
        $this->options = array_merge([
            'timeout' => 30,
            'retryAttempts' => 3,
            'retryDelay' => 1000, // milliseconds
            'maxLogLength' => 10000,
            'enableCrashLogging' => true,
            'connectTimeout' => 10,
            'sslVerify' => true,
            'userAgent' => self::USER_AGENT
        ], $config['options'] ?? []);
        
        // Initialize metrics
        $this->metrics = [
            'logsSent' => 0,
            'logsSuccess' => 0,
            'logsError' => 0,
            'startTime' => time(),
            'lastHeartbeat' => null
        ];
        
        $this->isConnected = false;
        
        // Setup error handling
        if ($this->options['enableCrashLogging']) {
            $this->setupErrorHandling();
        }
        
        // Initial connection test
        $this->testConnection();
    }
    
    /**
     * Test connection to the API
     * 
     * @return bool
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->makeRequest([
                'action' => 'logger',
                'request' => 'health'
            ]);
            
            $this->isConnected = $response['success'] ?? false;
            
            if ($this->isConnected) {
                $this->emit('connected', $response);
            }
            
            return $this->isConnected;
            
        } catch (Exception $e) {
            $this->isConnected = false;
            $this->emit('error', $e);
            return false;
        }
    }
    
    /**
     * Send a log entry
     * 
     * @param string $logLevel Log level (success, info, warning, error, critical)
     * @param string $message Log message
     * @param mixed $context Additional context (array or string)
     * @return array|null API response
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function sendLog(string $logLevel, string $message, $context = null): ?array
    {
        // Validation
        $validLevels = ['success', 'info', 'warning', 'error', 'critical'];
        if (!in_array($logLevel, $validLevels)) {
            throw new InvalidArgumentException("Invalid log level: {$logLevel}");
        }
        
        if (empty($message)) {
            throw new InvalidArgumentException('Message cannot be empty');
        }
        
        // Truncate message if too long
        if (strlen($message) > $this->options['maxLogLength']) {
            $message = substr($message, 0, $this->options['maxLogLength']) . '... [TRUNCATED]';
        }
        
        // Prepare log data
        $logData = [
            'action' => 'logger',
            'request' => 'new_log',
            'app_id' => $this->appId,
            'api_key' => $this->apiKey,
            'logLevel' => $logLevel,
            'message' => $message
        ];
        
        if ($context !== null) {
            $logData['context'] = is_string($context) ? $context : json_encode($context);
        }
        
        try {
            // Send with retry
            $result = $this->makeRequestWithRetry($logData);
            
            // Update metrics
            $this->metrics['logsSent']++;
            $this->metrics['logsSuccess']++;
            
            $this->emit('logSent', [
                'level' => $logLevel,
                'message' => $message,
                'result' => $result
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            $this->metrics['logsError']++;
            $this->emit('logError', [
                'level' => $logLevel,
                'message' => $message,
                'error' => $e
            ]);
            throw $e;
        }
    }
    
    /**
     * Log success message
     * 
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function logSuccess(string $message, $context = null): ?array
    {
        return $this->sendLog('success', $message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function logInfo(string $message, $context = null): ?array
    {
        return $this->sendLog('info', $message, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function logWarning(string $message, $context = null): ?array
    {
        return $this->sendLog('warning', $message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function logError(string $message, $context = null): ?array
    {
        return $this->sendLog('error', $message, $context);
    }
    
    /**
     * Log critical message
     * 
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function logCritical(string $message, $context = null): ?array
    {
        return $this->sendLog('critical', $message, $context);
    }
    
    /**
     * Send heartbeat
     * 
     * @return array API response
     * @throws Exception
     */
    public function sendHeartbeat(): array
    {
        try {
            $data = [
                'action' => 'logger',
                'request' => 'heartbeat',
                'app_id' => $this->appId,
                'api_key' => $this->apiKey
            ];
            
            $result = $this->makeRequest($data);
            $this->metrics['lastHeartbeat'] = time();
            $this->isConnected = true;
            
            $this->emit('heartbeat', $result);
            return $result;
            
        } catch (Exception $e) {
            $this->isConnected = false;
            $this->emit('heartbeatError', $e);
            throw $e;
        }
    }
    
    /**
     * Get local metrics
     * 
     * @return array
     */
    public function getMetrics(): array
    {
        return array_merge($this->metrics, [
            'isConnected' => $this->isConnected,
            'uptime' => time() - $this->metrics['startTime']
        ]);
    }
    
    /**
     * Log exceptions automatically
     * 
     * @param Throwable $throwable
     * @param string $level Log level (error by default)
     * @return array|null
     */
    public function logException(Throwable $throwable, string $level = 'error'): ?array
    {
        return $this->sendLog($level, 'Exception: ' . $throwable->getMessage(), [
            'exception' => get_class($throwable),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            'trace' => $throwable->getTraceAsString(),
            'code' => $throwable->getCode(),
            'timestamp' => date('c')
        ]);
    }
    
    /**
     * Make HTTP request with retry logic
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function makeRequestWithRetry(array $data): array
    {
        $lastException = null;
        
        for ($attempt = 1; $attempt <= $this->options['retryAttempts']; $attempt++) {
            try {
                return $this->makeRequest($data);
            } catch (Exception $e) {
                $lastException = $e;
                
                if ($attempt < $this->options['retryAttempts']) {
                    $delay = ($this->options['retryDelay'] * $attempt) / 1000; // convert to seconds
                    usleep($delay * 1000000); // usleep expects microseconds
                }
            }
        }
        
        throw $lastException;
    }
    
    /**
     * Make HTTP request
     * 
     * @param array $data
     * @return array
     * @throws Exception
     */
    private function makeRequest(array $data): array
    {
        $jsonData = json_encode($data);
        $url = rtrim($this->apiUrl, '/') . '/';
        
        // Configure cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->options['timeout'],
            CURLOPT_CONNECTTIMEOUT => $this->options['connectTimeout'],
            CURLOPT_SSL_VERIFYPEER => $this->options['sslVerify'],
            CURLOPT_USERAGENT => $this->options['userAgent'],
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($jsonData)
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Handle cURL errors
        if ($response === false) {
            throw new Exception("cURL error: {$curlError}");
        }
        
        // Decode JSON response
        $decodedResponse = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: {$response}");
        }
        
        // Handle HTTP status codes
        if ($httpCode >= 200 && $httpCode < 300) {
            if (isset($decodedResponse['success']) && $decodedResponse['success']) {
                return $decodedResponse['data'] ?? $decodedResponse;
            } else {
                throw new Exception($decodedResponse['error'] ?? 'API request failed');
            }
        } else {
            throw new Exception("HTTP {$httpCode}: " . ($decodedResponse['error'] ?? 'Request failed'));
        }
    }
    
    /**
     * Setup automatic error handling
     */
    private function setupErrorHandling(): void
    {
        // Handle fatal errors
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
                try {
                    $this->logCritical('Fatal Error - Application crashed', [
                        'error_type' => $error['type'],
                        'message' => $error['message'],
                        'file' => $error['file'],
                        'line' => $error['line'],
                        'timestamp' => date('c')
                    ]);
                } catch (Exception $e) {
                    // Ignore errors when logging fatal errors
                }
            }
        });
        
        // Handle uncaught exceptions
        set_exception_handler(function(Throwable $throwable) {
            try {
                $this->logException($throwable, 'critical');
            } catch (Exception $e) {
                // Ignore errors when logging exceptions
            }
        });
    }
    
    /**
     * Simple event system
     * 
     * @param string $event
     * @param callable $callback
     */
    public function on(string $event, callable $callback): void
    {
        if (!isset($this->eventListeners[$event])) {
            $this->eventListeners[$event] = [];
        }
        $this->eventListeners[$event][] = $callback;
    }
    
    /**
     * Emit an event
     * 
     * @param string $event
     * @param mixed $data
     */
    private function emit(string $event, $data = null): void
    {
        if (isset($this->eventListeners[$event])) {
            foreach ($this->eventListeners[$event] as $callback) {
                try {
                    call_user_func($callback, $data);
                } catch (Exception $e) {
                    // Ignore errors in callbacks
                }
            }
        }
    }
    
    /**
     * Generic log method with dynamic level
     * 
     * @param string $level
     * @param string $message
     * @param mixed $context
     * @return array|null
     */
    public function log(string $level, string $message, $context = null): ?array
    {
        return $this->sendLog($level, $message, $context);
    }
    
    /**
     * Check if logger is connected
     * 
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->isConnected;
    }
    
    /**
     * Get current configuration
     * 
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'app_id' => $this->appId,
            'api_url' => $this->apiUrl,
            'options' => $this->options,
            'version' => self::VERSION
        ];
    }
    
    /**
     * Performance measurement helper
     * 
     * @param string $operation
     * @param callable $callback
     * @param mixed $context
     * @return mixed
     */
    public function measure(string $operation, callable $callback, $context = null)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        try {
            $result = $callback();
            
            $executionTime = round((microtime(true) - $startTime) * 1000, 2); // in ms
            $memoryUsed = memory_get_usage() - $startMemory;
            
            $this->logInfo("Performance: {$operation}", array_merge([
                'execution_time_ms' => $executionTime,
                'memory_used_bytes' => $memoryUsed,
                'status' => 'success'
            ], $context ?? []));
            
            return $result;
            
        } catch (Exception $e) {
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            
            $this->logError("Performance: {$operation} FAILED", array_merge([
                'execution_time_ms' => $executionTime,
                'error' => $e->getMessage(),
                'status' => 'error'
            ], $context ?? []));
            
            throw $e;
        }
    }
}

/**
 * Factory function to create a logger easily
 * 
 * @param string $appId
 * @param string $apiKey
 * @param array $options
 * @return LoggerSimple
 */
function createLogger(string $appId, string $apiKey, array $options = []): LoggerSimple
{
    return new LoggerSimple([
        'app_id' => $appId,
        'api_key' => $apiKey,
        'options' => $options
    ]);
}