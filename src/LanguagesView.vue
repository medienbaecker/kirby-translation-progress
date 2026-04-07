<template>
	<k-panel-inside class="k-languages-view">
		<k-header>
			{{ $t("view.languages") }}
			<template #buttons>
				<k-view-buttons :buttons="buttons" />
			</template>
		</k-header>

		<template v-if="languages.length > 0">
			<k-section :headline="$t('languages.default')">
				<k-collection :items="primaryLanguage" />
			</k-section>

			<k-section :headline="$t('languages.secondary')">
				<k-collection
					v-if="secondaryLanguages.length"
					:items="secondaryLanguages"
				/>
				<k-empty
					v-else
					icon="translate"
					:disabled="!$panel.permissions.languages.create"
					@click="$dialog('languages/create')"
				>
					{{ $t("languages.secondary.empty") }}
				</k-empty>
			</k-section>
		</template>

		<template v-else-if="languages.length === 0">
			<k-empty
				icon="translate"
				:disabled="!$panel.permissions.languages.create"
				@click="$dialog('languages/create')"
			>
				{{ $t("languages.empty") }}
			</k-empty>
		</template>

		<template v-if="translationTree && translationTree.length">
			<k-section class="translation-progress-section" :headline="$t('translation-progress.title')">
				<k-stats size="medium" :reports="statsReports" />
			</k-section>

			<k-section>
				<div class="translation-progress-tree-container" @mouseleave="hoveredLang = null">
					<div class="translation-progress-tree-inner">
					<div class="translation-tree-header">
						<span class="translation-tree-header__pages" @mouseenter="hoveredLang = null">{{ $t("page") }}</span>
						<div class="translation-tree-header__percentages">
							<span
								v-for="lang in translationLanguages"
								:key="lang.code"
								class="translation-tree-header__cell"
								:class="{ 'translation-tree-header__cell--hover': hoveredLang === lang.code }"
								@mouseenter="hoveredLang = lang.code"
							><span
									class="translation-tree-header__lang"
									:class="{ 'translation-tree-header__lang--hover': hoveredLang === lang.code }"
									:title="lang.name"
								>{{ lang.code.toUpperCase() }}</span
							></span>
						</div>
					</div>
					<translation-tree
						:nodes="translationTree"
						:languages="translationLanguages"
						:default-language="translationDefaultLanguage"
						:hovered-lang="hoveredLang"
						@hover-lang="hoveredLang = $event"
					/>
					</div>
				</div>
			</k-section>
		</template>
	</k-panel-inside>
</template>

<script>
export default {
	extends: "k-languages-view",
	data() {
		return {
			translationTree: [],
			translationLanguages: [],
			translationDefaultLanguage: "",
			translationTotals: {},
			translationLastModified: {},
			hoveredLang: null,
		};
	},
	created() {
		this.loadTranslationStatus();
	},
	computed: {
		statsReports() {
			return this.translationLanguages.map((lang) => {
				const t = this.translationTotals[lang.code] || {};
				const pct = t.total ? Math.round((t.translated / t.total) * 100) : 0;
				const modified = this.translationLastModified[lang.code];

				let info = modified
					? this.$t("translation-progress.last-modified") + " " + new Date(modified).toLocaleDateString(this.$panel.translation.code, { year: "numeric", month: "short", day: "numeric" })
					: "";

				return {
					value: pct === 100 ? "100% 🎉" : pct + "%",
					label: lang.name,
					info: info,
					link: "languages/" + lang.code,
				};
			});
		},
	},
	methods: {
		async loadTranslationStatus() {
			try {
				const data = await this.$api.get("translation-progress/overview");
				this.translationTree = data.tree;
				this.translationLanguages = data.languages;
				this.translationDefaultLanguage = data.defaultLanguage;
				this.translationTotals = data.totals;
				this.translationLastModified = data.lastModified;
			} catch (error) {
				console.error("Translation status load failed:", error);
			}
		},
	},
};
</script>

<style>
.translation-progress-section {
	margin-block-start: var(--spacing-12);
}

.translation-progress-tree-container {
	background: var(--input-color-back);
	border-radius: var(--rounded);
	box-shadow: var(--shadow);
	overflow-x: auto;
}

.translation-progress-tree-inner {
	min-width: 100%;
	width: max-content;
}

.translation-tree-header {
	display: flex;
	align-items: center;
	height: var(--input-height);
	background: light-dark(var(--color-gray-100), var(--color-gray-800));
	border-block-end: 1px solid light-dark(rgba(0, 0, 0, 0.08), rgba(0, 0, 0, 0.375));
	border-start-start-radius: var(--rounded);
	border-start-end-radius: var(--rounded);
}
.translation-tree-header__pages {
	flex: 1;
	min-width: 12rem;
	padding-inline: var(--spacing-3);
	font-family: var(--font-mono);
	font-size: var(--text-xs);
	color: var(--color-text-dimmed);
	line-height: 1.25;
}
.translation-tree-header__percentages {
	display: flex;
	flex-shrink: 0;
	align-items: stretch;
}
.translation-tree-header__cell {
	display: flex;
	align-items: center;
}
.translation-tree-header__lang {
	display: inline-block;
	width: 2.75rem;
	margin: 0 0.25rem;
	text-align: center;
	font-size: var(--text-xs);
	color: var(--color-text-dimmed);
	line-height: 1.25;
}
.translation-tree-header__lang--hover {
	color: var(--color-focus);
}
</style>
