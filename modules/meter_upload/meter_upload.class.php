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

        $result = array(
            'status' => (!$error && $code >= 200 && $code < 300) ? 'ok' : 'error',
            'http_code' => $code,
            'msg' => $error ? $error : ('HTTP ' . $code),
            'sent' => count($payload['readings'])
        );
        $this->config['LAST_RESULT'] = date('Y-m-d H:i:s') . ' ' . $result['status'] . ': ' . $result['msg'];
        $this->saveConfig();
        return $result;
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
