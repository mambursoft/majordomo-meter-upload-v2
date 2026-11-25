<?php
/**
 * Meter Upload v2 - local-first utility meter readings uploader.
 * PHP 7.3 compatible, no external runtime dependencies.
 */
class meter_upload extends module
{
    function __construct()
    {
        $this->name = 'meter_upload';
        $this->title = 'Meter Upload v2';
        $this->module_category = '<#LANG_SECTION_SYSTEM#>';
        $this->checkInstalled();
    }

    function getConfig()
    {
        include_once(ROOT . 'config.php');
        $this->config = array();
        $rec = SQLSelectOne("SELECT ID, DATA FROM modules WHERE NAME='" . $this->name . "'");
        $this->module_id = isset($rec['ID']) ? (int)$rec['ID'] : 0;
        if (!empty($rec['DATA'])) {
            $this->config = @unserialize($rec['DATA']);
        }
        if (!is_array($this->config)) {
            $this->config = array();
        }
    }

    function saveConfig()
    {
        if (!$this->module_id) {
            $this->getConfig();
        }
        if (!$this->module_id) {
            return;
        }
        $rec = array('ID' => $this->module_id, 'DATA' => serialize($this->config));
        SQLUpdate('modules', $rec);
    }

    function admin(&$out)
    {
        $this->getConfig();

        $out['PROVIDER'] = isset($this->config['PROVIDER']) ? $this->config['PROVIDER'] : 'custom';
        $out['ENDPOINT'] = isset($this->config['ENDPOINT']) ? $this->config['ENDPOINT'] : '';
        $out['ACCOUNT'] = isset($this->config['ACCOUNT']) ? $this->config['ACCOUNT'] : '';
        $out['METERS'] = isset($this->config['METERS']) ? $this->config['METERS'] : '';
        $out['ENABLED'] = isset($this->config['ENABLED']) ? (int)$this->config['ENABLED'] : 0;
        $out['LAST_RESULT'] = isset($this->config['LAST_RESULT']) ? $this->config['LAST_RESULT'] : '';
        $out['UNIT'] = isset($this->config['UNIT']) ? $this->config['UNIT'] : 'kWh';
        $out['QUEUE_COUNT'] = count($this->loadQueue());
        $token = isset($this->config['TOKEN']) ? $this->config['TOKEN'] : '';
        $out['HAS_TOKEN'] = $token ? 1 : 0;
        $out['TOKEN_MASK'] = $token ? str_repeat('*', min(16, strlen($token))) : '';

        if ($this->mode == 'update') {
            $provider = preg_replace('/[^a-z0-9_-]/i', '', (string)gr('provider'));
            $endpoint = trim((string)gr('endpoint'));
            if ($endpoint && !preg_match('/^https?:\/\//i', $endpoint)) {
                $endpoint = 'https://' . $endpoint;
            }
            $this->config['PROVIDER'] = $provider ? $provider : 'custom';
            $this->config['ENDPOINT'] = $endpoint;
            $this->config['ACCOUNT'] = trim((string)gr('account'));
            $this->config['METERS'] = trim((string)gr('meters'));
            $this->config['ENABLED'] = gr('enabled') ? 1 : 0;
            $unit = (string)gr('unit');
            $this->config['UNIT'] = in_array($unit, array('kWh', 'm3', 'Gcal'), true) ? $unit : 'kWh';
            $new_token = trim((string)gr('token'));
            if ($new_token !== '') {
                $this->config['TOKEN'] = $new_token;
            }
            $this->saveConfig();
            $this->redirect('?');
        }
    }

    function usual(&$out)
    {
        $this->admin($out);
    }

    function parseMeters()
    {
        $result = array();
        $lines = preg_split('/\r?\n/', isset($this->config['METERS']) ? $this->config['METERS'] : '');
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '=') === false) {
                continue;
            }
            list($code, $title) = explode('=', $line, 2);
            $code = preg_replace('/[^A-Za-z0-9_-]/', '', $code);
            if ($code !== '') {
                $result[$code] = trim($title);
            }
        }
        return $result;
    }

    function queueFile()
    {
        return ROOT . 'cms/cached/meter_upload/queue.json';
    }

    function loadQueue()
    {
        $file = $this->queueFile();
        if (!is_file($file)) {
            return array();
        }
        $data = @json_decode((string)@file_get_contents($file), true);
        return is_array($data) ? $data : array();
    }

    function saveQueue($queue)
    {
        $file = $this->queueFile();
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $handle = @fopen($file, 'c');
        if (!$handle) {
            return false;
        }
        $ok = false;
        if (flock($handle, LOCK_EX)) {
            rewind($handle);
            ftruncate($handle, 0);
            $ok = (fwrite($handle, json_encode($queue)) !== false);
            fflush($handle);
            flock($handle, LOCK_UN);
        }
        fclose($handle);
        return $ok;
    }

    function api($params)
    {
        $this->getConfig();
        if (empty($this->config['ENABLED'])) {
            return array('status' => 'error', 'msg' => 'Module is disabled');
        }
        $endpoint = isset($this->config['ENDPOINT']) ? $this->config['ENDPOINT'] : '';
        if (!$endpoint) {
            return array('status' => 'error', 'msg' => 'Endpoint is not configured');
        }
        $values = isset($params['values']) && is_array($params['values']) ? $params['values'] : array();
        if (!$values) {
            return array('status' => 'error', 'msg' => 'No readings supplied');
        }
        $known = $this->parseMeters();
        $payload = array(
            'account' => isset($this->config['ACCOUNT']) ? $this->config['ACCOUNT'] : '',
            'provider' => isset($this->config['PROVIDER']) ? $this->config['PROVIDER'] : 'custom',
            'unit' => isset($this->config['UNIT']) ? $this->config['UNIT'] : 'kWh',
            'readings' => array()
        );
        foreach ($values as $code => $value) {
            $code = preg_replace('/[^A-Za-z0-9_-]/', '', (string)$code);
            if ($known && !isset($known[$code])) {
                continue;
            }
            $payload['readings'][$code] = (float)$value;
        }
        if (!$payload['readings']) {
            return array('status' => 'error', 'msg' => 'No known meters in readings');
        }

        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if (!empty($this->config['TOKEN'])) {
            $headers[] = 'Authorization: Bearer ' . $this->config['TOKEN'];
        }
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $body = curl_exec($ch);
        $error = curl_error($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = (!$error && $code >= 200 && $code < 300);
        $result = array(
            'status' => $ok ? 'ok' : 'error',
            'http_code' => $code,
            'msg' => $error ? $error : ('HTTP ' . $code),
            'sent' => count($payload['readings'])
        );
        if (!$ok) {
            $queue = $this->loadQueue();
            $queue[] = array('time' => time(), 'payload' => $payload);
            $this->saveQueue($queue);
            $result['queued'] = count($queue);
            $result['msg'] .= ' (locally queued)';
        } else {
            $queue = $this->loadQueue();
            if ($queue) {
                $result['queue_remaining'] = count($queue);
            }
        }
        $this->config['LAST_RESULT'] = date('Y-m-d H:i:s') . ' ' . $result['status'] . ': ' . $result['msg'];
        $this->saveConfig();
        return $result;
    }

    function syncQueue()
    {
        $this->getConfig();
        $queue = $this->loadQueue();
        if (!$queue) {
            return array('status' => 'ok', 'msg' => 'Queue is empty', 'remaining' => 0);
        }
        $remaining = array();
        $sent = 0;
        foreach ($queue as $item) {
            if (!isset($item['payload']) || !is_array($item['payload'])) {
                continue;
            }
            $res = $this->api($item['payload']);
            if ($res['status'] === 'ok') {
                $sent++;
            } else {
                $remaining[] = $item;
            }
        }
        $this->saveQueue($remaining);
        return array('status' => 'ok', 'msg' => 'Sent: ' . $sent, 'remaining' => count($remaining));
    }

    function install($data = '')
    {
        parent::install();
    }

    function dbInstall($data)
    {
        return '';
    }
}
