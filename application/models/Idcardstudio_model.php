<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Idcardstudio_model extends CI_Model
{
    public function isReady()
    {
        return $this->db->table_exists('id_card_designs')
            && $this->db->table_exists('id_card_design_versions');
    }

    public function listTemplates($subjectType)
    {
        $table = $this->legacyTable($subjectType);
        if ($this->isReady()) {
            $this->db->select($table . '.*, d.id AS studio_design_id, d.orientation AS studio_orientation, '
                . 'd.width_mm AS studio_width_mm, d.height_mm AS studio_height_mm, '
                . 'd.draft_version_id, d.published_version_id, d.updated_at AS studio_updated_at');
        } else {
            $this->db->select($table . '.*');
        }
        $this->db->from($table);
        if ($this->isReady()) {
            $this->db->join('id_card_designs d', 'd.legacy_template_id = ' . $table . '.id AND d.subject_type = '
                . $this->db->escape($subjectType) . ' AND d.is_active = 1', 'left', false);
        }
        $this->db->order_by($table . '.id', 'DESC');
        return $this->db->get()->result();
    }

    public function getLegacyTemplate($subjectType, $legacyId)
    {
        return $this->db->where('id', (int) $legacyId)->get($this->legacyTable($subjectType))->row();
    }

    public function getDesignForLegacy($subjectType, $legacyId)
    {
        if (!$this->isReady()) {
            return null;
        }
        return $this->db
            ->where('subject_type', $subjectType)
            ->where('legacy_template_id', (int) $legacyId)
            ->where('is_active', 1)
            ->get('id_card_designs')
            ->row();
    }

    public function getDesign($designId)
    {
        if (!$this->isReady()) {
            return null;
        }
        return $this->db->where('id', (int) $designId)->where('is_active', 1)->get('id_card_designs')->row();
    }

    public function getVersion($versionId)
    {
        if (!$this->isReady() || !$versionId) {
            return null;
        }
        return $this->db->where('id', (int) $versionId)->get('id_card_design_versions')->row();
    }

    public function getDraft($designId)
    {
        $design = $this->getDesign($designId);
        return $design ? $this->getVersion($design->draft_version_id) : null;
    }

    public function getPublishedForLegacy($subjectType, $legacyId)
    {
        if (!$this->isReady()) {
            return null;
        }
        return $this->db->select('d.*, v.id AS version_id, v.version_no, v.schema_version, '
                . 'v.front_json, v.back_json, v.print_settings_json, v.checksum, v.published_at')
            ->from('id_card_designs d')
            ->join('id_card_design_versions v', "v.id = d.published_version_id AND v.state = 'published'", 'inner', false)
            ->where('d.subject_type', $subjectType)
            ->where('d.legacy_template_id', (int) $legacyId)
            ->where('d.is_active', 1)
            ->get()
            ->row();
    }

    public function createFromLegacy($subjectType, $legacy, array $documents, $staffId)
    {
        $existing = $this->getDesignForLegacy($subjectType, $legacy->id);
        if ($existing) {
            return $existing;
        }

        $now = date('Y-m-d H:i:s');
        $dimensions = $documents['dimensions'];
        $frontJson = json_encode($documents['front'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $backJson = json_encode($documents['back'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $printJson = json_encode($documents['printSettings'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $checksum = hash('sha256', json_encode(array(
            'front' => $documents['front'],
            'back' => $documents['back'],
            'printSettings' => $documents['printSettings'],
        ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        $this->db->trans_begin();
        $this->db->insert('id_card_designs', array(
            'subject_type' => $subjectType,
            'legacy_template_id' => (int) $legacy->id,
            'title' => (string) $legacy->title,
            'width_mm' => $dimensions['width_mm'],
            'height_mm' => $dimensions['height_mm'],
            'orientation' => $dimensions['orientation'],
            'is_active' => 1,
            'created_by' => $staffId ?: null,
            'updated_by' => $staffId ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $designId = (int) $this->db->insert_id();

        if (!$designId) {
            $this->db->trans_rollback();
            return false;
        }

        $this->db->insert('id_card_design_versions', array(
            'design_id' => $designId,
            'version_no' => 1,
            'state' => 'draft',
            'schema_version' => 1,
            'front_json' => $frontJson,
            'back_json' => $backJson,
            'print_settings_json' => $printJson,
            'checksum' => $checksum,
            'created_by' => $staffId ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $versionId = (int) $this->db->insert_id();

        $this->db->where('id', $designId)->update('id_card_designs', array('draft_version_id' => $versionId));
        $this->audit($designId, $versionId, $staffId, 'convert_legacy', array('legacy_template_id' => (int) $legacy->id));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return false;
        }
        $this->db->trans_commit();
        return $this->getDesign($designId);
    }

    /**
     * Optimistically save the active draft. A changed checksum means another
     * browser saved first and the caller must reload instead of overwriting it.
     */
    public function saveDraft($designId, array $payload, $expectedChecksum, $staffId)
    {
        $this->db->trans_begin();
        $design = $this->lockedDesign($designId);
        if (!$design) {
            $this->db->trans_rollback();
            return array('status' => 'missing');
        }
        $draft = $this->lockedVersion($design->draft_version_id);
        if (!$draft || $draft->state !== 'draft') {
            $this->db->trans_rollback();
            return array('status' => 'invalid_state');
        }
        if ($expectedChecksum === '' || !hash_equals((string) $draft->checksum, (string) $expectedChecksum)) {
            $this->db->trans_rollback();
            return array('status' => 'conflict', 'checksum' => $draft->checksum);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('id', $draft->id)->where('state', 'draft')->update('id_card_design_versions', array(
            'front_json' => $payload['front_json'],
            'back_json' => $payload['back_json'],
            'print_settings_json' => $payload['print_settings_json'],
            'checksum' => $payload['checksum'],
            'updated_at' => $now,
        ));
        $this->db->where('id', $design->id)->update('id_card_designs', array(
            'title' => $payload['title'],
            'width_mm' => $payload['width_mm'],
            'height_mm' => $payload['height_mm'],
            'orientation' => $payload['orientation'],
            'updated_by' => $staffId ?: null,
            'updated_at' => $now,
        ));
        $this->audit($design->id, $draft->id, $staffId, 'save_draft', array('checksum' => $payload['checksum']));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => 'error');
        }
        $this->db->trans_commit();
        return array('status' => 'saved', 'checksum' => $payload['checksum'], 'updated_at' => $now);
    }

    public function publish($designId, $expectedChecksum, $staffId)
    {
        $this->db->trans_begin();
        $design = $this->lockedDesign($designId);
        if (!$design) {
            $this->db->trans_rollback();
            return array('status' => 'missing');
        }
        $draft = $this->lockedVersion($design->draft_version_id);
        if (!$draft || $draft->state !== 'draft') {
            $this->db->trans_rollback();
            return array('status' => 'invalid_state');
        }
        if ($expectedChecksum === '' || !hash_equals((string) $draft->checksum, (string) $expectedChecksum)) {
            $this->db->trans_rollback();
            return array('status' => 'conflict', 'checksum' => $draft->checksum);
        }

        $now = date('Y-m-d H:i:s');
        if ($design->published_version_id) {
            $this->db->where('id', (int) $design->published_version_id)
                ->where('state', 'published')
                ->update('id_card_design_versions', array('state' => 'archived', 'updated_at' => $now));
        }
        $this->db->where('id', (int) $draft->id)->where('state', 'draft')->update('id_card_design_versions', array(
            'state' => 'published',
            'published_by' => $staffId ?: null,
            'published_at' => $now,
            'updated_at' => $now,
        ));

        $nextVersion = $this->nextVersionNumber($design->id);
        $this->db->insert('id_card_design_versions', array(
            'design_id' => (int) $design->id,
            'version_no' => $nextVersion,
            'state' => 'draft',
            'schema_version' => (int) $draft->schema_version,
            'front_json' => $draft->front_json,
            'back_json' => $draft->back_json,
            'print_settings_json' => $draft->print_settings_json,
            'checksum' => $draft->checksum,
            'created_by' => $staffId ?: null,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        $newDraftId = (int) $this->db->insert_id();

        $this->db->where('id', (int) $design->id)->update('id_card_designs', array(
            'published_version_id' => (int) $draft->id,
            'draft_version_id' => $newDraftId,
            'updated_by' => $staffId ?: null,
            'updated_at' => $now,
        ));
        $this->audit($design->id, $draft->id, $staffId, 'publish', array('published_version' => (int) $draft->version_no));

        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => 'error');
        }
        $this->db->trans_commit();
        return array(
            'status' => 'published',
            'published_version_id' => (int) $draft->id,
            'draft_version_id' => $newDraftId,
            'draft_checksum' => $draft->checksum,
        );
    }

    public function useLegacyRenderer($designId, $expectedPublishedVersionId, $staffId)
    {
        $this->db->trans_begin();
        $design = $this->lockedDesign($designId);
        if (!$design) {
            $this->db->trans_rollback();
            return array('status' => 'missing');
        }
        if (!$design->published_version_id) {
            $this->db->trans_rollback();
            return array('status' => 'legacy');
        }
        if ((int) $expectedPublishedVersionId < 1
            || (int) $design->published_version_id !== (int) $expectedPublishedVersionId) {
            $this->db->trans_rollback();
            return array('status' => 'conflict', 'published_version_id' => (int) $design->published_version_id);
        }
        $now = date('Y-m-d H:i:s');
        $publishedId = (int) $design->published_version_id;
        $this->db->where('id', $publishedId)->where('state', 'published')
            ->update('id_card_design_versions', array('state' => 'archived', 'updated_at' => $now));
        $this->db->where('id', (int) $design->id)->update('id_card_designs', array(
            'published_version_id' => null,
            'updated_by' => $staffId ?: null,
            'updated_at' => $now,
        ));
        $this->audit($design->id, $publishedId, $staffId, 'use_legacy_renderer', array('archived_version_id' => $publishedId));
        if ($this->db->trans_status() === false) {
            $this->db->trans_rollback();
            return array('status' => 'error');
        }
        $this->db->trans_commit();
        return array('status' => 'legacy');
    }

    public function history($designId)
    {
        return $this->db->where('design_id', (int) $designId)
            ->order_by('version_no', 'DESC')
            ->get('id_card_design_versions')
            ->result();
    }

    public function addAsset(array $record)
    {
        $this->db->insert('id_card_design_assets', $record);
        return (int) $this->db->insert_id();
    }

    public function getAsset($assetId, $designId = null)
    {
        $this->db->where('id', (int) $assetId)->where('deleted_at IS NULL', null, false);
        if ($designId !== null) {
            $this->db->where('design_id', (int) $designId);
        }
        return $this->db->get('id_card_design_assets')->row();
    }

    public function listAssets($designId)
    {
        return $this->db->where('design_id', (int) $designId)
            ->where('deleted_at IS NULL', null, false)
            ->order_by('id', 'DESC')
            ->get('id_card_design_assets')
            ->result();
    }

    public function publishedAssets($designId)
    {
        $result = array();
        foreach ($this->listAssets($designId) as $asset) {
            $result[(int) $asset->id] = $asset;
        }
        return $result;
    }

    public function audit($designId, $versionId, $staffId, $action, array $summary = array())
    {
        if (!$this->db->table_exists('id_card_design_audit')) {
            return;
        }
        $this->db->insert('id_card_design_audit', array(
            'design_id' => (int) $designId,
            'version_id' => $versionId ? (int) $versionId : null,
            'staff_id' => $staffId ?: null,
            'action' => $action,
            'summary_json' => $summary ? json_encode($summary, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    private function lockedDesign($designId)
    {
        return $this->db->query('SELECT * FROM `id_card_designs` WHERE `id` = ? AND `is_active` = 1 FOR UPDATE', array((int) $designId))->row();
    }

    private function lockedVersion($versionId)
    {
        return $this->db->query('SELECT * FROM `id_card_design_versions` WHERE `id` = ? FOR UPDATE', array((int) $versionId))->row();
    }

    private function nextVersionNumber($designId)
    {
        $row = $this->db->select_max('version_no', 'maximum')->where('design_id', (int) $designId)->get('id_card_design_versions')->row();
        return $row ? ((int) $row->maximum + 1) : 1;
    }

    private function legacyTable($subjectType)
    {
        if ($subjectType === 'student') {
            return 'id_card';
        }
        if ($subjectType === 'staff') {
            return 'staff_id_card';
        }
        throw new InvalidArgumentException('ID card type must be student or staff.');
    }
}
