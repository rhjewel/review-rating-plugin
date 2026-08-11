(function () {
	"use strict";

	function syncRatingLabels(group) {
		var inputs = group.querySelectorAll("input[type='radio']");

		inputs.forEach(function (input) {
			input.addEventListener("change", function () {
				group.dataset.rating = input.value;
			});
		});
	}

	function clearSuccessfulSubmissionStatus() {
		var url;

		if (!window.history || !window.history.replaceState) {
			return;
		}

		url = new URL(window.location.href);

		if (url.searchParams.get("review_rating_status") !== "success") {
			return;
		}

		url.searchParams.delete("review_rating_status");
		window.history.replaceState(window.history.state, "", url.toString());
	}

	function initReviewImageUpload(input) {
		var config = window.reviewRatingPlugin || {};
		var max = parseInt(input.dataset.max || "3", 10);
		var form = input.form;
		var field = input.closest(".rrp-image-field");
		var preview = field ? field.querySelector("[data-review-rating-image-preview]") : null;
		var previewUrls = [];

		function clearPreviews() {
			previewUrls.forEach(function (url) {
				URL.revokeObjectURL(url);
			});

			previewUrls = [];

			if (preview) {
				preview.innerHTML = "";
				preview.hidden = true;
			}
		}

		function renderPreviews() {
			clearPreviews();

			if (!preview || !input.files || input.files.length > max) {
				return;
			}

			Array.prototype.forEach.call(input.files, function (file) {
				var item;
				var image;
				var url;

				if (file.type.indexOf("image/") !== 0) {
					return;
				}

				url = URL.createObjectURL(file);
				previewUrls.push(url);

				item = document.createElement("li");
				image = document.createElement("img");
				image.src = url;
				image.alt = file.name;
				image.title = file.name;
				item.appendChild(image);
				preview.appendChild(item);
			});

			preview.hidden = !preview.children.length;
		}

		function validateImageCount() {
			var count = input.files ? input.files.length : 0;
			var message = count > max ? (config.imageLimitText || "You can upload a maximum of " + max + " images.") : "";

			input.setCustomValidity(message);

			return !message;
		}

		input.addEventListener("change", function () {
			renderPreviews();

			if (!validateImageCount()) {
				input.reportValidity();
			}
		});

		if (form) {
			form.addEventListener("submit", function (event) {
				if (!validateImageCount()) {
					event.preventDefault();
					input.reportValidity();
				}
			});
		}
	}

	function initAdminImageSettingsToggle(toggle) {
		var limitField = document.querySelector("[data-review-rating-image-limit]");

		if (!limitField) {
			return;
		}

		function updateVisibility() {
			limitField.hidden = !toggle.checked;
		}

		toggle.addEventListener("change", updateVisibility);
		updateVisibility();
	}

	function initCriteriaRepeater(repeater) {
		var addButton = document.querySelector("[data-review-rating-add]");
		var max = parseInt(repeater.dataset.max || "10", 10);
		var optionName = "review_rating_settings";

		function rows() {
			return Array.prototype.slice.call(repeater.querySelectorAll("[data-review-rating-row]"));
		}

		function updateState() {
			var currentRows = rows();
			var atLimit = currentRows.length >= max;

			if (addButton) {
				addButton.disabled = atLimit;
			}

			currentRows.forEach(function (row) {
				var removeButton = row.querySelector("[data-review-rating-remove]");

				if (removeButton) {
					removeButton.disabled = currentRows.length <= 1;
				}
			});
		}

		function createRow() {
			var id = "criteria_" + Date.now();
			var row = document.createElement("div");

			row.className = "review-rating-criteria-row";
			row.dataset.reviewRatingRow = "";
			row.innerHTML = [
				'<span class="review-rating-criteria-handle" aria-hidden="true">☰</span>',
				'<label class="review-rating-criteria-toggle">',
				'<input type="checkbox" name="' + optionName + '[criteria_rows][' + id + '][enabled]" value="1" checked>',
				"<span>Active</span>",
				"</label>",
				'<label class="review-rating-criteria-key">',
				'<span class="screen-reader-text">Criteria key</span>',
				'<input type="text" name="' + optionName + '[criteria_rows][' + id + '][key]" value="" placeholder="criteria_key">',
				"</label>",
				'<label class="review-rating-criteria-label">',
				'<span class="screen-reader-text">Criteria label</span>',
				'<input type="text" name="' + optionName + '[criteria_rows][' + id + '][label]" value="" placeholder="Criteria label" required>',
				"</label>",
				'<button type="button" class="button-link-delete review-rating-remove-criteria" data-review-rating-remove>Remove</button>'
			].join("");

			return row;
		}

		if (addButton) {
			addButton.addEventListener("click", function () {
				if (rows().length >= max) {
					updateState();
					return;
				}

				var row = createRow();
				repeater.appendChild(row);
				updateState();

				var labelInput = row.querySelector(".review-rating-criteria-label input");

				if (labelInput) {
					labelInput.focus();
				}
			});
		}

		repeater.addEventListener("click", function (event) {
			if (!event.target.matches("[data-review-rating-remove]")) {
				return;
			}

			if (rows().length <= 1) {
				updateState();
				return;
			}

			event.target.closest("[data-review-rating-row]").remove();
			updateState();
		});

		updateState();
	}

	function initLoadMore(button) {
		button.addEventListener("click", function () {
			var list = button.closest(".rrp-list");
			var items = list ? list.querySelector("[data-review-rating-items]") : null;
			var config = window.reviewRatingPlugin || {};
			var originalText = button.textContent;
			var formData = new FormData();

			if (!items || !config.ajaxUrl || button.disabled) {
				return;
			}

			button.disabled = true;
			button.textContent = button.dataset.loadingText || "Loading...";

			formData.append("action", "review_rating_load_more");
			formData.append("nonce", config.loadMoreNonce || "");
			formData.append("post_id", button.dataset.postId || "");
			formData.append("offset", button.dataset.offset || "0");
			formData.append("limit", button.dataset.limit || "3");

			fetch(config.ajaxUrl, {
				method: "POST",
				credentials: "same-origin",
				body: formData
			})
				.then(function (response) {
					return response.json();
				})
				.then(function (response) {
					if (!response || !response.success || !response.data) {
						throw new Error("Invalid response");
					}

					if (response.data.html) {
						items.insertAdjacentHTML("beforeend", response.data.html);
					}

					button.dataset.offset = response.data.next_offset || button.dataset.offset;

					if (!response.data.has_more) {
						button.closest(".rrp-load-more-wrap").remove();
						return;
					}

					button.disabled = false;
					button.textContent = config.loadMoreText || originalText;
				})
				.catch(function () {
					button.disabled = false;
					button.textContent = originalText;
					window.alert(config.errorText || "Could not load reviews. Please try again.");
				});
		});
	}

	document.addEventListener("DOMContentLoaded", function () {
		clearSuccessfulSubmissionStatus();
		document.querySelectorAll(".rrp-rating-input").forEach(syncRatingLabels);
		document.querySelectorAll("[data-review-rating-images]").forEach(initReviewImageUpload);
		document.querySelectorAll("[data-review-rating-image-toggle]").forEach(initAdminImageSettingsToggle);
		document.querySelectorAll("[data-review-rating-repeater]").forEach(initCriteriaRepeater);
		document.querySelectorAll("[data-review-rating-load-more]").forEach(initLoadMore);
	});
})();
