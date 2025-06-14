# 📊 Logger Simple - PHP Client

[![PHP Version](https://img.shields.io/badge/PHP-%5E7.4%7C%5E8.0-blue)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Latest Version](https://img.shields.io/badge/version-1.0.0-brightgreen)](https://github.com/logger-simple/php-logger-simple)

Official PHP client for Logger Simple API. A simple and robust solution to send logs from your PHP applications to Logger Simple.

> **Note**: This PHP client is designed for **sending logs only**. To retrieve logs and view statistics, use the [Logger Simple Dashboard](https://logger-simple.com) or access the API directly.

## ✨ Features

- 🚀 **Easy to use** - Integration in just a few lines of code
- 🔄 **Automatic retry** - Intelligent handling of network failures
- 📊 **5 log levels** - Success, Info, Warning, Error, Critical
- 💓 **Heartbeat** - Application health monitoring
- 🛡️ **Error handling** - Automatic capture of exceptions and fatal errors
- 📈 **Local metrics** - Built-in performance statistics
- ⚡ **Performance measurement** - Helper to track code execution
- 🎯 **Events** - Event system to customize behavior

## 📦 Installation

### Manual Installation

1. Download the `Logger.php` file
2. Include it in your project:

```php
require_once 'path/to/Logger.php';
```

### Via Composer (Future)

```bash
composer require logger-simple/php-client
```

## ⚙️ Quick Setup

```php
<?php
require_once 'Logger.php';

// Simple configuration
$logger = new LoggerSimple([
    'app_id' => 'your_app_id',
    'api_key' => 'your_api_key'
]);

// Or using helper function
$logger = createLogger('your_app_id', 'your_api_key');
```

## 🚀 Basic Usage

### Sending Logs

```php
<?php
// Different log levels
$logger->logSuccess('User logged in successfully', [
    'user_id' => 12345,
    'ip' => $_SERVER['REMOTE_ADDR']
]);

$logger->logInfo('Processing started', ['batch_id' => 'batch_001']);

$logger->logWarning('Memory limit reached', [
    'memory_usage' => memory_get_usage(),
    'limit' => ini_get('memory_limit')
]);

$logger->logError('Database connection failed', [
    'database' => 'mysql',
    'error' => 'Connection timeout'
]);

$logger->logCritical('Service unavailable', [
    'service' => 'payment_gateway',
    'downtime' => '5 minutes'
]);
```

### Exception Handling

```php
<?php
try {
    // Code that might throw an exception
    $result = riskyOperation();
} catch (Exception $e) {
    // Automatic exception logging
    $logger->logException($e);
    
    // Or with custom level
    $logger->logException($e, 'critical');
}
```

### Heartbeat and Monitoring

```php
<?php
// Send heartbeat to indicate application is running
try {
    $response = $logger->sendHeartbeat();
    echo "Heartbeat sent: " . json_encode($response);
} catch (Exception $e) {
    echo "Heartbeat failed: " . $e->getMessage();
}
```

## 🔧 Advanced Configuration

```php
<?php
$logger = new LoggerSimple([
    'app_id' => 'your_app_id',
    'api_key' => 'your_api_key',
    'api_url' => 'https://api.logger-simple.com/', // Custom URL
    'options' => [
        'timeout' => 30,              // Timeout in seconds
        'retryAttempts' => 3,         // Number of retry attempts
        'retryDelay' => 1000,         // Delay between retries (ms)
        'maxLogLength' => 10000,      // Max message length
        'enableCrashLogging' => true, // Auto-capture errors
        'connectTimeout' => 10,       // Connection timeout
        'sslVerify' => true,          // SSL verification
        'userAgent' => 'MyApp/1.0'    // Custom User-Agent
    ]
]);
```

## ⚡ Performance Measurement

```php
<?php
// Automatically measure function execution
$result = $logger->measure('database_query', function() {
    // Code to measure
    return $database->query('SELECT * FROM users');
}, ['query_type' => 'select']);

// Measure complex operation
$logger->measure('image_processing', function() use ($image) {
    $image->resize(800, 600);
    $image->compress(85);
    return $image->save();
}, [
    'original_size' => $image->getSize(),
    'target_size' => '800x600'
]);
```

## 🎯 Event System

```php
<?php
// Listen to logger events
$logger->on('connected', function($data) {
    echo "Connected to API successfully!\n";
});

$logger->on('logSent', function($data) {
    echo "Log sent: {$data['level']} - {$data['message']}\n";
});

$logger->on('error', function($error) {
    echo "Logger error: " . $error->getMessage() . "\n";
});

$logger->on('heartbeat', function($response) {
    echo "Heartbeat OK: " . json_encode($response) . "\n";
});
```

## 📈 Local Metrics

```php
<?php
// Get local logger metrics (not from API)
$metrics = $logger->getMetrics();

echo "Local Logger Metrics:\n";
echo "- Logs sent: " . $metrics['logsSent'] . "\n";
echo "- Logs successful: " . $metrics['logsSuccess'] . "\n";
echo "- Logs failed: " . $metrics['logsError'] . "\n";
echo "- Connected: " . ($metrics['isConnected'] ? 'Yes' : 'No') . "\n";
echo "- Uptime: " . $metrics['uptime'] . " seconds\n";
```

## 🌐 Framework Integration Examples

### With Laravel

```php
<?php
// In a Service Provider
use App\Services\LoggerSimple;

class AppServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(LoggerSimple::class, function () {
            return new LoggerSimple([
                'app_id' => config('logger.app_id'),
                'api_key' => config('logger.api_key')
            ]);
        });
    }
}

// In a Controller
class UserController extends Controller
{
    private $logger;
    
    public function __construct(LoggerSimple $logger)
    {
        $this->logger = $logger;
    }
    
    public function login(Request $request)
    {
        try {
            $user = Auth::attempt($request->only('email', 'password'));
            
            $this->logger->logSuccess('Login successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
            
            return response()->json(['success' => true]);
        } catch (Exception $e) {
            $this->logger->logException($e);
            return response()->json(['error' => 'Login failed'], 500);
        }
    }
}
```

### With Symfony

```php
<?php
// services.yaml
services:
    LoggerSimple:
        arguments:
            $config:
                app_id: '%env(LOGGER_APP_ID)%'
                api_key: '%env(LOGGER_API_KEY)%'

// In a Controller
class ApiController extends AbstractController
{
    private LoggerSimple $logger;
    
    public function __construct(LoggerSimple $logger)
    {
        $this->logger = $logger;
    }
    
    #[Route('/api/process', methods: ['POST'])]
    public function process(Request $request): JsonResponse
    {
        return $this->logger->measure('api_process', function() use ($request) {
            $data = json_decode($request->getContent(), true);
            
            $this->logger->logInfo('API processing started', [
                'endpoint' => '/api/process',
                'data_size' => strlen($request->getContent())
            ]);
            
            // Processing...
            $result = $this->processData($data);
            
            $this->logger->logSuccess('API processing completed', [
                'result_count' => count($result)
            ]);
            
            return new JsonResponse($result);
        });
    }
}
```

### Standalone Script

```php
<?php
require_once 'Logger.php';

// Configuration
$logger = createLogger('batch_processor_abc123', 'sk_live_xyz789');

// Log startup
$logger->logInfo('Batch processing started', [
    'script' => basename(__FILE__),
    'started_at' => date('c')
]);

try {
    // Main processing
    $results = $logger->measure('batch_processing', function() {
        $processed = 0;
        $files = glob('data/*.csv');
        
        foreach ($files as $file) {
            // Process each file
            $processed += processFile($file);
        }
        
        return $processed;
    });
    
    $logger->logSuccess('Batch processing completed', [
        'files_processed' => count($files),
        'records_processed' => $results,
        'finished_at' => date('c')
    ]);
    
} catch (Exception $e) {
    $logger->logException($e, 'critical');
    exit(1);
}

function processFile($file) {
    global $logger;
    
    $logger->logInfo('Processing file', ['file' => basename($file)]);
    
    // Processing logic...
    $recordCount = 1000; // Example
    
    return $recordCount;
}
```

## 🔍 Debugging and Troubleshooting

### Debug Mode

```php
<?php
$logger = new LoggerSimple([
    'app_id' => 'test_app',
    'api_key' => 'test_key',
    'options' => [
        'timeout' => 5,        // Short timeout for tests
        'retryAttempts' => 1   // Fewer attempts
    ]
]);

// Test connection
if (!$logger->testConnection()) {
    echo "Cannot connect to API\n";
    exit(1);
}

// Check configuration
$config = $logger->getConfig();
print_r($config);
```

### Robust Error Handling

```php
<?php
try {
    $logger->logInfo('Connection test');
} catch (Exception $e) {
    // Fallback: local logging on failure
    error_log("Logger Simple failed: " . $e->getMessage());
    
    // Or use PHP's system logger
    syslog(LOG_INFO, "Fallback log: Connection test");
}
```

## 🧪 Testing

### Connection Test

```bash
php -r "
require 'Logger.php';
\$logger = createLogger('test_app', 'test_key');
echo \$logger->testConnection() ? 'OK' : 'FAIL';
"
```

### Complete Test Script

```php
<?php
require_once 'Logger.php';

echo "🧪 Logger Simple PHP Test\n";
echo "=========================\n\n";

try {
    $logger = createLogger('test_app_123', 'sk_test_1234567890');
    
    echo "✅ Logger created\n";
    
    if ($logger->testConnection()) {
        echo "✅ API connection OK\n";
    } else {
        echo "❌ API connection failed\n";
        exit(1);
    }
    
    // Test logs
    $logger->logSuccess('PHP test successful', ['timestamp' => time()]);
    echo "✅ SUCCESS log sent\n";
    
    $logger->logInfo('Information test', ['test' => true]);
    echo "✅ INFO log sent\n";
    
    // Test heartbeat
    $logger->sendHeartbeat();
    echo "✅ Heartbeat sent\n";
    
    // Display metrics
    $metrics = $logger->getMetrics();
    echo "\n📊 Local Metrics:\n";
    echo "- Logs sent: {$metrics['logsSent']}\n";
    echo "- Connected: " . ($metrics['isConnected'] ? 'Yes' : 'No') . "\n";
    
    echo "\n✅ All tests passed!\n";
    echo "\n🌐 Check your logs on the Logger Simple Dashboard\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
```

## 📋 Requirements

- **PHP 7.4+** or **PHP 8.0+**
- **cURL extension** enabled
- **JSON extension** enabled (usually included)
- **HTTPS outbound access** on port 443

## 🔧 API Configuration

Before using the logger, you need to:

1. **Create an application** in your Logger Simple dashboard
2. **Get your credentials**: `app_id` and `api_key`
3. **Configure your PHP application** with these credentials

### Create Test Application (SQL)

If you have database access, you can create a test application:

```sql
INSERT INTO apps (app_id, api_key, name, description, user_id, max_logs_per_hour, is_active, created_at) 
VALUES ('test_app_123', 'sk_test_1234567890', 'Test Application', 'Application for testing', 'test_user_123', 1000, 1, NOW());
```

## 🌟 Best Practices

### Log Levels Usage

- **logSuccess()** - Important successful operations
- **logInfo()** - Tracking information  
- **logWarning()** - Situations requiring attention
- **logError()** - Recoverable errors
- **logCritical()** - Critical errors

### Performance Tips

- Use `measure()` to track performance bottlenecks
- Send heartbeats regularly to monitor application health
- Use automatic exception logging with `logException()`
- Configure appropriate timeout and retry settings
- View logs and statistics on the [Logger Simple Dashboard](https://logger-simple.com)

### Error Handling

```php
<?php
// Always wrap logger calls in try-catch for critical applications
try {
    $logger->logError('Database error', ['error' => $dbError]);
} catch (Exception $e) {
    // Fallback logging
    error_log("Logger failed: " . $e->getMessage());
}
```

## 📚 Resources

- 🌐 **Official Website**: [https://logger-simple.com](https://logger-simple.com)
- 📖 **API Documentation**: [https://docs.logger-simple.com](https://docs.logger-simple.com)
- 🐛 **Report Bug**: [GitHub Issues](https://github.com/logger-simple/php-logger-simple/issues)
- 💬 **Support**: [hello@logger-simple.com](mailto:hello@logger-simple.com)

## 📄 License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## 🔄 Changelog

### v1.0.0 (2025-01-15)
- ✨ Initial release
- 🚀 Complete Logger Simple API support
- 📊 Built-in metrics system
- ⚡ Performance measurement helper
- 🎯 Event system
- 🛡️ Automatic error handling

---

**Made with ❤️ by the Logger Simple Team**