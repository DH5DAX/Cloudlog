# CloudLog API: Worked Before Check

## Endpoint: `/api/worked_before`

This API endpoint performs comprehensive "worked before" checks for a given callsign, including checks by band, mode, and DXCC entity. This is particularly useful for contest logging applications and dupe checking systems.

### Method
`POST`

### URL
`https://your-cloudlog-instance.com/index.php/api/worked_before`

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
| `callsign` | string | Yes | Callsign to check (e.g., "W1AW") |
| `frequency` | string | Yes | Frequency in MHz (e.g., "14.205") |
| `mode` | string | Yes | Amateur radio mode (e.g., "SSB", "CW", "FT8") |

### Request Example

```json
{
  "key": "your-api-key-here",
  "logbook_public_slug": "my-contest-log",
  "callsign": "W1AW",
  "frequency": "14.205",
  "mode": "SSB"
}
```

### Response Format

The API returns a JSON object with detailed "worked before" status for both the specific callsign and its DXCC entity.

```json
{
  "callsign": {
    "any": boolean,      // Worked this callsign before on any band/mode
    "band": boolean,     // Worked this callsign before on this band
    "mode": boolean,     // Worked this callsign before in this mode
    "bandMode": boolean  // Worked this callsign before on this band and mode
  },
  "dxcc": {
    "any": boolean,      // Worked this DXCC entity before on any band/mode
    "band": boolean,     // Worked this DXCC entity before on this band
    "mode": boolean,     // Worked this DXCC entity before in this mode
    "bandMode": boolean  // Worked this DXCC entity before on this band and mode
  },
  "info": {
    "band": string,      // Band derived from frequency (e.g., "20M")
    "dxccEntity": string // DXCC entity name for the callsign
  }
}
```

### Response Example

```json
{
  "callsign": {
    "any": true,
    "band": false,
    "mode": true,
    "bandMode": false
  },
  "dxcc": {
    "any": true,
    "band": true,
    "mode": true,
    "bandMode": true
  },
  "info": {
    "band": "20M",
    "dxccEntity": "United States"
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success - returns worked before data |
| 400 | Bad Request - Invalid JSON, missing required fields, or invalid frequency |
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

#### Contest Logging
Perfect for real-time dupe checking during contests:
- Check if a callsign is a complete duplicate (`callsign.bandMode: true`)
- Identify new multipliers (`dxcc.band: false` or `dxcc.mode: false`)
- Validate whether a contact is worth pursuing

#### General Logging
Useful for everyday logging applications:
- Prevent accidental duplicate QSOs
- Track progress toward awards (DXCC, WAS, etc.)
- Provide context about previous contacts

#### Integration with Contest Software
Can be integrated with contest logging software to provide real-time feedback:
- Red flag for exact duplicates
- Yellow flag for same callsign on different band/mode
- Green light for new multipliers

### Implementation Example (JavaScript)

```javascript
async function checkWorkedBefore(callsign, frequency, mode) {
    const response = await fetch('/index.php/api/worked_before', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            key: 'your-api-key',
            logbook_public_slug: 'your-logbook-slug',
            callsign: callsign.toUpperCase(),
            frequency: frequency,
            mode: mode.toUpperCase()
        })
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.reason);
    }

    return await response.json();
}

// Usage example
checkWorkedBefore('W1AW', '14.205', 'SSB')
    .then(result => {
        if (result.callsign.bandMode) {
            console.log('DUPE: Exact duplicate contact');
        } else if (result.callsign.any) {
            console.log('INFO: Worked this callsign before on different band/mode');
        }
        
        if (!result.dxcc.band) {
            console.log('NEW: New multiplier for this band!');
        }
    })
    .catch(error => {
        console.error('API Error:', error.message);
    });
```

### Implementation Example (Python)

```python
import requests
import json

def check_worked_before(callsign, frequency, mode, api_key, logbook_slug):
    url = "https://your-cloudlog-instance.com/index.php/api/worked_before"
    
    payload = {
        "key": api_key,
        "logbook_public_slug": logbook_slug,
        "callsign": callsign.upper(),
        "frequency": str(frequency),
        "mode": mode.upper()
    }
    
    response = requests.post(url, json=payload)
    
    if response.status_code == 200:
        return response.json()
    else:
        error_data = response.json()
        raise Exception(f"API Error: {error_data['reason']}")

# Usage example
try:
    result = check_worked_before('W1AW', 14.205, 'SSB', 'your-api-key', 'your-logbook')
    
    if result['callsign']['bandMode']:
        print('DUPE: Exact duplicate contact')
    elif result['callsign']['any']:
        print('INFO: Worked this callsign before on different band/mode')
    
    if not result['dxcc']['band']:
        print('NEW: New multiplier for this band!')
        
except Exception as e:
    print(f"Error: {e}")
```

### Notes

- Satellite contacts (where `COL_PROP_MODE = 'SAT'`) are excluded from all checks
- Mode checking uses the main mode category (e.g., PSK31 and FT8 both map to "DATA")
- Frequency is automatically converted to the appropriate amateur radio band
- All callsigns are automatically converted to uppercase for consistent matching
- The API respects logbook permissions and only checks within the specified logbook's station locations

### Rate Limiting

Please be respectful with API usage. For high-frequency applications (like real-time contest logging), consider implementing local caching to reduce API calls for recently checked callsigns.
