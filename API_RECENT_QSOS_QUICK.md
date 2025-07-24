# CloudLog Recent QSOs API - Quick Reference

## Endpoint
**POST** `/api/recent_qsos`

## Authentication
Include API key with read permissions in request body.

## Request
```json
{
  "key": "your-api-key",
  "logbook_public_slug": "your-logbook-slug",
  "limit": 10
}
```

### Parameters
- `key` (required): Your API authentication key
- `logbook_public_slug` (required): Target logbook identifier  
- `limit` (optional): Number of QSOs to return (1-50, default: 10)

## Response
Returns array of recent QSO objects, newest first:

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
    "comment": "Nice signal!"
  }
]
```

### Response Fields
- `timestamp`: ISO 8601 UTC datetime
- `callsign`: Contacted station
- `name`: Operator name (empty if unknown)
- `band`: Amateur radio band
- `mode`: Operating mode (includes submode)
- `rst_sent/rst_rcvd`: Signal reports
- `country`: DXCC entity
- `comment`: QSO notes (empty if none)

## Status Codes
- **200**: Success
- **400**: Bad request (invalid JSON/missing fields)
- **401**: Unauthorized (invalid API key)
- **404**: Logbook not found

## Quick Examples

### JavaScript
```javascript
fetch('/index.php/api/recent_qsos', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        key: 'your-key',
        logbook_public_slug: 'your-logbook',
        limit: 5
    })
}).then(r => r.json()).then(qsos => console.log(qsos));
```

### Python
```python
import requests
response = requests.post('https://your-site.com/index.php/api/recent_qsos', 
    json={'key': 'your-key', 'logbook_public_slug': 'your-logbook', 'limit': 5})
qsos = response.json()
```

### cURL
```bash
curl -X POST https://your-site.com/index.php/api/recent_qsos \
  -H "Content-Type: application/json" \
  -d '{"key":"your-key","logbook_public_slug":"your-logbook","limit":5}'
```

## Use Cases
- Dashboard widgets showing recent activity
- Mobile app QSO displays
- Contest logging software integration
- Station monitoring systems
- QSL management tracking
