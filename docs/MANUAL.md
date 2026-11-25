# Manual

1. Install the module in MajorDoMo and open **Control Panel → Meter Upload v2**.
2. Choose a provider preset or enter a custom endpoint URL (HTTPS recommended).
3. Enter account ID and meter IDs, one per line: `code=1234567890`.
4. Store the access token. It is masked in the interface and never shown again.
5. Call from a scenario:

```php
$mu = getModule('meter_upload');
$result = $mu->api(array(
  'values' => array('1234567890' => 123.45)
));
if ($result['status'] !== 'ok') {
  DebMes('Meter upload failed: ' . $result['msg'], 'meter_upload');
}
```

The module never contacts the provider until `api()` or the manual test button is used.