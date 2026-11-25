# Manual

## Ukrainian providers

The setting **Provider** includes presets for Ukrainian services: `Київенерго (UA)`, `Київгаз (UA)`, `Укргаз (UA)`, `Львівгаз (UA)`. The exact endpoint and token must be taken from your personal account: providers change URLs and confirmation flows, so the module stores them as settings instead of hardcoding.

Supported units: `кВт·год (kWh)`, `м³ (m3)`, `Гкал (Gcal)`. Use the unit that your provider expects.

## Setup

1. Install the module and open **Control Panel → Meter Upload v2**.
2. Choose a provider preset or enter a custom endpoint URL (HTTPS recommended).
3. Enter account ID and meter IDs, one per line: `code=1234567890`.
4. Store the access token. It is masked in the interface and never shown again.
5. Enable upload.

## Offline queue (important for Ukrainian power outages)

If the provider is unreachable, the readings are saved to `cms/cached/meter_upload/queue.json` instead of being lost. Use the **Sync queue** button or call `syncQueue()` from a scenario after connectivity returns:

```php
$mu = getModule('meter_upload');
$result = $mu->syncQueue();
```

## Scenario API

```php
$mu = getModule('meter_upload');
$result = $mu->api(array(
  'values' => array('1234567890' => 123.45)
));
if ($result['status'] !== 'ok') {
  DebMes('Meter upload failed: ' . $result['msg'], 'meter_upload');
}
```

The module uses local time from MajorDoMo (`Europe/Kyiv` is recommended) and never contacts the provider until `api()` or a test button is used.
