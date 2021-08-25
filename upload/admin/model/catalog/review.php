<?php
class ModelCatalogReview extends Model {
    public function install() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "review_addiction_info` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `review_id` INT(11) NOT NULL,
              `hidden_customer_info` INT(5) NOT NULL,
              `help_count` INT(11) NOT NULL DEFAULT 0,
              `not_help_count` INT(11) NOT NULL DEFAULT 0,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		");
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "review_image` (
              `id` INT(11) NOT NULL AUTO_INCREMENT,
              `review_id` INT(11) NOT NULL,
              `catalog` varchar(255) NOT NULL,
              `filename` varchar(255) NOT NULL,
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
		");
    }

    public function uninstall() {
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_addiction_info`");
        $this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "review_image`");
    }

	public function addReview($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (int)$data['rating'] . "', status = '" . (int)$data['status'] . "', date_added = '" . $this->db->escape($data['date_added']) . "'");

		$review_id = $this->db->getLastId();

		$this->cache->delete('product');

		return $review_id;
	}

	public function editReview($review_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "review SET author = '" . $this->db->escape($data['author']) . "', product_id = '" . (int)$data['product_id'] . "', text = '" . $this->db->escape(strip_tags($data['text'])) . "', rating = '" . (int)$data['rating'] . "', status = '" . (int)$data['status'] . "', date_added = '" . $this->db->escape($data['date_added']) . "', date_modified = NOW() WHERE review_id = '" . (int)$review_id . "'");

		$this->cache->delete('product');
	}

	public function deleteReview($review_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "review WHERE review_id = '" . (int)$review_id . "'");

		$this->cache->delete('product');
	}

	public function getReview($review_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT pd.name FROM " . DB_PREFIX . "product_description pd WHERE pd.product_id = r.product_id AND pd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS product FROM " . DB_PREFIX . "review r WHERE r.review_id = '" . (int)$review_id . "'");

		return $query->row;
	}

	public function getReviews($data = array()) {
		$sql = "SELECT r.review_id, pd.name, r.author, r.rating, r.status, r.date_added FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		$sort_data = array(
			'pd.name',
			'r.author',
			'r.rating',
			'r.status',
			'r.date_added'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY r.date_added";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getTotalReviews($data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review r LEFT JOIN " . DB_PREFIX . "product_description pd ON (r.product_id = pd.product_id) WHERE pd.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_product'])) {
			$sql .= " AND pd.name LIKE '" . $this->db->escape($data['filter_product']) . "%'";
		}

		if (!empty($data['filter_author'])) {
			$sql .= " AND r.author LIKE '" . $this->db->escape($data['filter_author']) . "%'";
		}

		if (isset($data['filter_status']) && $data['filter_status'] !== '') {
			$sql .= " AND r.status = '" . (int)$data['filter_status'] . "'";
		}

		if (!empty($data['filter_date_added'])) {
			$sql .= " AND DATE(r.date_added) = DATE('" . $this->db->escape($data['filter_date_added']) . "')";
		}

		$query = $this->db->query($sql);

		return $query->row['total'];
	}

	public function getTotalReviewsAwaitingApproval() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review WHERE status = '0'");

		return $query->row['total'];
	}

    public function getReviewAddictionInfo($review_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_addiction_info WHERE review_id = '" . (int)$review_id . "'");

        return $query->row;
    }

    public function addReviewAddictionInfo($review_id, $hidden_customer_info) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "review_addiction_info SET review_id = '" . (int)$review_id . "', `hidden_customer_info` = '" . (int)$hidden_customer_info . "'");
    }

    public function editReviewAddictionInfo($review_id, $hidden_customer_info) {
        $this->db->query("UPDATE " . DB_PREFIX . "review_addiction_info SET `hidden_customer_info` = '" . (int)$hidden_customer_info . "' WHERE review_id = '" . (int)$review_id . "'");
    }

    public function deleteReviewAddictionInfo($review_id) {
        $this->db->query("DELETE FROM " . DB_PREFIX . "review_addiction_info WHERE review_id = '" . (int)$review_id . "'");
    }

    public function getReviewImages($review_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");

        return $query->rows;
    }

    public function getTotalReviewImages($review_id) {
        $query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");

        return $query->row['total'];;
    }

    public function getReviewImage($image_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_image WHERE id = '" . (int)$image_id . "'");

        return $query->rows;
    }

    public function addReviewImage($review_id, $catalog, $filename) {
        $this->db->query("INSERT INTO " . DB_PREFIX . "review_image SET review_id = '" . (int)$review_id . "', `catalog` = '" . $this->db->escape($catalog) . "', filename = '" . $this->db->escape($filename) . "'");
    }

    public function deleteReviewImages($review_id) {
        $images = $this->getReviewImages($review_id);

        foreach ($images as $image) {
            unlink( DIR_IMAGE . '\\' . $image['catalog'] . $image['filename']);
        }

        $this->db->query("DELETE FROM " . DB_PREFIX . "review_image WHERE review_id = '" . (int)$review_id . "'");
    }

    public function deleteReviewImage($image_id) {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "review_image WHERE id = '" . (int)$image_id . "'");

        unlink( DIR_IMAGE . '\\' . $query->row['catalog'] . $query->row['filename']);

        $this->db->query("DELETE FROM " . DB_PREFIX . "review_image WHERE id = '" . (int)$image_id . "'");
    }
}