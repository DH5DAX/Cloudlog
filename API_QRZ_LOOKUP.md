# CloudLog API: QRZ.com Callsign Lookup

## Endpoint: `/api/qrz_lookup`

**HTTP Method:** POST  
**Content-Type:** application/json  
**Authentication:** API Key Required

## Description

This endpoint provides comprehensive callsign lookup using QRZ.com's XML API. It returns detailed information about a callsign including personal information, location data, and profile images.

## Prerequisites

- Valid CloudLog API key with read permissions
- QRZ.com subscription (required for XML API access)
- QRZ.com credentials configured in CloudLog system configuration

## Request Format

### Headers
```
Content-Type: application/json
```

### Request Body
```json
{
  "key": "your-api-key",
  "callsign": "W1AW",
  "station_profile_id": 1
}
```

### Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `key` | string | Yes | Your CloudLog API authentication key |
| `callsign` | string | Yes | Amateur radio callsign to lookup |
| `station_profile_id` | integer | No | Your station profile ID for distance/bearing calculation |

## Response Format

### Success Response (HTTP 200)

```json
{
  "callsign": "W1AW",
  "name": "Hiram Percy Maxim",
  "gridsquare": "FN31pr",
  "city": "Newington",
  "lat": "41.714775",
  "long": "-72.727260",
  "dxcc": "291",
  "iota": "",
  "qslmgr": "",
  "image": "https://s3.amazonaws.com/files.qrz.com/q/w1aw/w1aw.jpg",
  "state": "CT",
  "us_county": "Hartford",
  "distance": "1247 km",
  "bearing": "072°"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `callsign` | string | The callsign as stored in QRZ.com |
| `name` | string | Operator name (first name only or full name based on privacy settings) |
| `gridsquare` | string | Maidenhead grid locator (up to 8 characters) |
| `city` | string | City or location |
| `lat` | string | Latitude in decimal degrees |
| `long` | string | Longitude in decimal degrees |
| `dxcc` | string | DXCC entity code |
| `iota` | string | IOTA island reference (if applicable) |
| `qslmgr` | string | QSL manager callsign (if applicable) |
| `image` | string | URL to operator's profile image |
| `state` | string | State/province (US callsigns only) |
| `us_county` | string | US county (US callsigns only) |
| `distance` | string | Distance from your station in kilometers (if station_profile_id provided) |
| `bearing` | string | Bearing from your station in degrees (if station_profile_id provided) |

**Note:** Fields may be empty strings if data is not available or not published by the operator. Distance and bearing fields are only populated when `station_profile_id` parameter is provided and the looked-up callsign has a valid gridsquare.

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success - returns callsign data |
| 400 | Bad Request - Invalid JSON format |
| 401 | Unauthorized - Missing or invalid API key, missing callsign |
| 404 | Not Found - Callsign not found in QRZ.com database |
| 503 | Service Unavailable - QRZ.com credentials not configured or authentication failed |

## Error Response Format

```json
{
  "status": "failed",
  "reason": "Description of the error"
}
```

### Common Error Messages

- `"Invalid JSON format"` - Request body is not valid JSON
- `"Missing or invalid API key"` - API key is missing or invalid
- `"Missing callsign parameter"` - Callsign field is missing or empty
- `"Station profile does not belong to API key owner"` - Invalid station profile ID for this user
- `"QRZ.com credentials not configured in system"` - System administrator needs to configure QRZ.com credentials
- `"QRZ.com authentication failed"` - Invalid QRZ.com credentials
- `"Callsign W1AW not found in QRZ.com database"` - Callsign not found
- `"QRZ.com error: [error]"` - Error returned by QRZ.com API

## Setup Requirements

### 1. Configure QRZ.com Credentials

System administrators must configure QRZ.com credentials in the CloudLog configuration file:

1. Edit `application/config/config.php`
2. Set the following values:
   ```php
   $config['qrz_username'] = "your-qrz-username";
   $config['qrz_password'] = "your-qrz-password";
   $config['use_fullname'] = false; // or true for full names
   ```
3. Save the configuration file

### 2. QRZ.com Subscription

This endpoint requires a valid QRZ.com XML subscription. Free QRZ.com accounts do not have access to the XML API.

## Usage Examples

### JavaScript (fetch)
```javascript
const response = await fetch('https://your-cloudlog.com/index.php/api/qrz_lookup', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    key: 'your-api-key',
    callsign: 'W1AW',
    station_profile_id: 1
  })
});

const data = await response.json();
console.log('QRZ Data:', data);
```

### Python
```python
import requests
import json

url = 'https://your-cloudlog.com/index.php/api/qrz_lookup'
payload = {
    'key': 'your-api-key',
    'callsign': 'W1AW',
    'station_profile_id': 1
}

response = requests.post(url, json=payload)
data = response.json()

print(f"Callsign: {data.get('callsign')}")
print(f"Name: {data.get('name')}")
print(f"Grid: {data.get('gridsquare')}")
print(f"Distance: {data.get('distance')}")
print(f"Bearing: {data.get('bearing')}")
print(f"Image: {data.get('image')}")
```

### cURL
```bash
curl -X POST https://your-cloudlog.com/index.php/api/qrz_lookup \
  -H "Content-Type: application/json" \
  -d '{
    "key": "your-api-key",
    "callsign": "W1AW",
    "station_profile_id": 1
  }'
```

### PHP
```php
<?php
$url = 'https://your-cloudlog.com/index.php/api/qrz_lookup';
$data = [
    'key' => 'your-api-key',
    'callsign' => 'W1AW',
    'station_profile_id' => 1
];

$options = [
    'http' => [
        'header' => "Content-type: application/json\r\n",
        'method' => 'POST',
        'content' => json_encode($data)
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);
$qrz_data = json_decode($result, true);

echo "Callsign: " . $qrz_data['callsign'] . "\n";
echo "Name: " . $qrz_data['name'] . "\n";
echo "Distance: " . $qrz_data['distance'] . "\n";
echo "Bearing: " . $qrz_data['bearing'] . "\n";
echo "Image: " . $qrz_data['image'] . "\n";
?>
```

## Use Cases

### Profile Display
Perfect for displaying detailed operator information:
- Contest logging software operator lookup
- QSL card applications showing operator photos
- Station information displays

### Location Services
Geographic information for mapping and navigation:
- Plotting contacts on maps with distance rings
- Distance and bearing calculations for beam headings
- Grid square validation and path analysis
- Propagation predictions and path planning

### QSL Management
QSL routing and management:
- Automatic QSL manager detection
- Address lookup for direct QSL cards
- Profile image inclusion in electronic QSLs

### Contest Logging
Enhanced contest logging features:
- Operator name display during logging
- Real-time callsign validation
- Geographic information for multiplier tracking

## Rate Limiting

- Respects QRZ.com rate limits
- Session management reduces API calls
- Cached authentication sessions

## Privacy Considerations

- Returns only publicly available QRZ.com data
- Respects operator privacy settings
- Name field content depends on QRZ.com privacy configuration
- Some operators may limit visible information

## Related Endpoints

- [`/api/lookup`](API_LOOKUP.md) - Local callsign lookup
- [`/api/worked_before`](API_WORKED_BEFORE.md) - Worked before checking
- [`/api/recent_qsos`](API_RECENT_QSOS.md) - Recent QSO retrieval

## Support

For issues with this endpoint:
1. Verify QRZ.com credentials are configured
2. Ensure QRZ.com subscription is active
3. Check CloudLog API key permissions
4. Review error messages for specific issues
