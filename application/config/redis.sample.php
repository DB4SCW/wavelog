<?php
/**
 * Redis Configuration
 * 
 * Copy this sample file to redis.php and adjust settings as needed.
 * 
 */

// Connection type: 'tcp' for TCP/IP, 'unix' for Unix domain sockets
$config['socket_type'] = 'tcp';

// Redis server hostname / IP address
$config['host'] = '127.0.0.1';

// Redis server port (default: 6379)
$config['port'] = 6379;

// Redis authentication password (NULL if no password required)
$config['password'] = NULL;

// Unix domain socket path (only used if socket_type is set to 'unix')
// Example: '/var/run/redis.sock'
$config['socket'] = '/var/run/redis.sock';

// Connection timeout in seconds (0 = no timeout)
$config['timeout'] = 0;

// Redis database number (0-15 by default)
// Useful for separating different applications or data types
// $config['database'] = 0;

// Connection retry delay in milliseconds (useful for persistent connections)
// $config['retry_interval'] = 100;