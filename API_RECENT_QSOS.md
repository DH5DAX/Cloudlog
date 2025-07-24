# CloudLog API: Recent QSOs

## Endpoint: `/api/recent_qsos`

This API endpoint retrieves the most recent QSO entries from a logbook in a compact format, perfect for displaying recent activity or status monitoring.

### Method
`POST`

### URL
`https://your-cloudlog-instance.com/index.php/api/recent_qsos`

### Headers
```
Content-Type: application/json
```

### Authentication
Requires a valid API key with read permissions.

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | string | Yes | Your CloudLog API authentication key |
| `logbook_public_slug` | string | Yes | Public slug identifier for the target logbook |
| `limit` | integer | No | Number of QSOs to return (default: 10, max: 50) |

### Request Example

```json
{
  "key": "your-api-key-here",
  "logbook_public_slug": "my-contest-log",
  "limit": 5
}
```

### Response Format

The API returns a JSON array containing the most recent QSO entries, ordered by date/time (newest first).

Each QSO entry contains:

| Field | Type | Description |
|-------|------|-------------|
| `timestamp` | string | QSO date and time in ISO 8601 format (UTC) |
| `callsign` | string | Contacted callsign |
| `name` | string | Operator name (empty string if not available) |
| `band` | string | Amateur radio band (e.g., "20M", "40M") |
| `mode` | string | Amateur radio mode (includes submode if available) |
| `rst_sent` | string | RST signal report sent |
| `rst_rcvd` | string | RST signal report received |
| `country` | string | DXCC entity/country name |
| `comment` | string | QSO comment/notes (empty string if none) |

### Response Example

```json
[
  {
    "timestamp": "2025-07-24T14:30:00Z",
    "callsign": "W1AW",
    "name": "John Smith",
    "band": "20M",
    "mode": "SSB",
    "rst_sent": "59",
    "rst_rcvd": "59",
    "country": "United States",
    "comment": "Nice signal from Connecticut!"
  },
  {
    "timestamp": "2025-07-24T14:25:00Z",
    "callsign": "VK3ABC",
    "name": "Mike Johnson",
    "band": "20M",
    "mode": "CW",
    "rst_sent": "599",
    "rst_rcvd": "579",
    "country": "Australia",
    "comment": ""
  },
  {
    "timestamp": "2025-07-24T14:20:00Z",
    "callsign": "JA1XYZ",
    "name": "",
    "band": "15M",
    "mode": "FT8",
    "rst_sent": "-10",
    "rst_rcvd": "-15",
    "country": "Japan",
    "comment": "FT8 QSO via digital mode"
  }
]
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success - returns recent QSO data |
| 400 | Bad Request - Invalid JSON or missing required fields |
| 401 | Unauthorized - Missing or invalid API key |
| 404 | Not Found - Logbook not found or empty |

### Error Response Format

```json
{
  "status": "failed",
  "reason": "Description of the error"
}
```

### Use Cases

#### Station Monitoring
Perfect for monitoring recent activity:
- Display last few QSOs on a dashboard
- Check if the station is active
- Monitor contest progress

#### Mobile Applications
Great for mobile ham radio apps:
- Quick activity overview
- Recent contacts display
- Sync status checking

#### Integration with Other Systems
Can be used by:
- Contest logging software for activity monitoring
- QSL managers for recent contact tracking
- Club websites for member activity displays

### Implementation Example (JavaScript)

```javascript
async function getRecentQSOs(limit = 10) {
    const response = await fetch('/index.php/api/recent_qsos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            key: 'your-api-key',
            logbook_public_slug: 'your-logbook-slug',
            limit: limit
        })
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.reason);
    }

    return await response.json();
}

// Usage example
getRecentQSOs(5)
    .then(qsos => {
        qsos.forEach(qso => {
            const nameInfo = qso.name ? ` (${qso.name})` : '';
            console.log(`${qso.timestamp}: ${qso.callsign}${nameInfo} on ${qso.band} ${qso.mode}`);
        });
    })
    .catch(error => {
        console.error('API Error:', error.message);
    });
```

### Implementation Example (Python)

```python
import requests
import json
from datetime import datetime

def get_recent_qsos(api_key, logbook_slug, limit=10):
    url = "https://your-cloudlog-instance.com/index.php/api/recent_qsos"
    
    payload = {
        "key": api_key,
        "logbook_public_slug": logbook_slug,
        "limit": limit
    }
    
    response = requests.post(url, json=payload)
    
    if response.status_code == 200:
        return response.json()
    else:
        error_data = response.json()
        raise Exception(f"API Error: {error_data['reason']}")

# Usage example
try:
    recent_qsos = get_recent_qsos('your-api-key', 'your-logbook', 5)
    
    print("Recent QSOs:")
    for qso in recent_qsos:
        # Parse ISO timestamp
        qso_time = datetime.fromisoformat(qso['timestamp'].replace('Z', '+00:00'))
        name_info = f" ({qso['name']})" if qso['name'] else ""
        print(f"{qso_time.strftime('%Y-%m-%d %H:%M')}: {qso['callsign']}{name_info} "
              f"({qso['country']}) on {qso['band']} {qso['mode']}")
        
except Exception as e:
    print(f"Error: {e}")
```

### Implementation Example (PHP)

```php
<?php
function getRecentQSOs($apiKey, $logbookSlug, $limit = 10) {
    $url = 'https://your-cloudlog-instance.com/index.php/api/recent_qsos';
    
    $data = [
        'key' => $apiKey,
        'logbook_public_slug' => $logbookSlug,
        'limit' => $limit
    ];
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    if ($result === FALSE) {
        throw new Exception('API request failed');
    }
    
    return json_decode($result, true);
}

// Usage example
try {
    $recentQSOs = getRecentQSOs('your-api-key', 'your-logbook', 5);
    
    echo "Recent QSOs:\n";
    foreach ($recentQSOs as $qso) {
        $nameInfo = !empty($qso['name']) ? " ({$qso['name']})" : "";
        echo sprintf("%s: %s%s (%s) on %s %s\n",
            $qso['timestamp'],
            $qso['callsign'],
            $nameInfo,
            $qso['country'],
            $qso['band'],
            $qso['mode']
        );
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
```

### Notes

- Timestamps are returned in UTC (ISO 8601 format with 'Z' suffix)
- The `mode` field includes submode when available (e.g., "FT8" instead of "DATA")
- Empty fields are returned as empty strings rather than null
- Results are ordered by date/time, with most recent first
- For QSOs logged on the same date/time, ordering uses the primary key as secondary sort
- The limit parameter prevents excessive data transfer and server load

### Rate Limiting

This endpoint is suitable for regular polling but please be considerate:
- For real-time applications, polling every 30-60 seconds is reasonable
- For dashboard updates, polling every 5-10 minutes is sufficient
- Consider implementing caching on the client side to reduce API calls
