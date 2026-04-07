<template>
	<ul class="translation-tree" :style="{ '--translation-tree-level': level }" role="tree">
		<li v-for="(node, nodeIndex) in nodes" :key="node.id" role="treeitem"
			:aria-expanded="hasChildren(node) ? isOpen(node.id) : undefined">
			<div class="translation-tree__row" :class="{ 'translation-tree__row--odd': flatIndex(nodeIndex) % 2 === 1 }">
				<div class="translation-tree__branch" @mouseenter="$emit('hover-lang', null)" @focusin="$emit('hover-lang', null)">
					<button v-if="hasChildren(node)" class="translation-tree__toggle" type="button" @click="toggle(node.id)">
						<k-icon :type="isOpen(node.id) ? 'angle-down' : 'angle-right'" />
					</button>
					<span v-else class="translation-tree__toggle" />

					<k-link v-if="node.link" class="translation-tree__folder" :to="'/' + node.link + '?language=' + defaultLanguage">
						<k-icon
							:type="node.icon || 'status-' + node.status"
							:class="'translation-tree__status' + (node.status ? ' translation-tree__status--' + node.status : '')"
						/>
						<span class="translation-tree__label">{{ node.title }}</span>
					</k-link>
					<span v-else class="translation-tree__folder translation-tree__folder--static">
						<k-icon :type="node.icon || 'page'" class="translation-tree__status" />
						<span class="translation-tree__label">{{ node.title }}</span>
					</span>
				</div>

				<div class="translation-tree__percentages">
					<template v-if="node.langs">
						<span v-for="lang in languages" :key="lang.code"
							class="translation-tree__percent-cell"
							:class="{ 'translation-tree__percent-cell--hover': hoveredLang === lang.code }"
							@mouseenter="$emit('hover-lang', lang.code)"
							@focusin="$emit('hover-lang', lang.code)"
						>
							<k-link
								class="translation-tree__percent"
								:class="'translation-tree__percent--' + langStatus(node, lang.code)"
								:title="lang.name + ': ' + langTitle(node, lang.code)"
								:to="percentLink(node, lang.code)"
							>{{ percentage(node, lang.code) }}%</k-link>
						</span>
					</template>
					<template v-else>
						<span v-for="lang in languages" :key="lang.code"
							class="translation-tree__percent-cell"
							:class="{ 'translation-tree__percent-cell--hover': hoveredLang === lang.code }"
							@mouseenter="$emit('hover-lang', lang.code)"
						><span class="translation-tree__percent translation-tree__percent--empty" /></span>
					</template>
				</div>
			</div>

			<translation-tree v-if="hasChildren(node) && isOpen(node.id)" :nodes="node.children" :languages="languages"
				:default-language="defaultLanguage" :hovered-lang="hoveredLang" :level="level + 1" :start-index="childStartIndex(nodeIndex)"
				@hover-lang="$emit('hover-lang', $event)" />
		</li>
	</ul>
</template>

<script>
export default {
	name: "translation-tree",
	props: {
		nodes: { type: Array, default: () => [] },
		languages: { type: Array, default: () => [] },
		defaultLanguage: { type: String, default: "en" },
		hoveredLang: { default: null },
		level: { type: Number, default: 0 },
		startIndex: { type: Number, default: 0 },
	},
	emits: ["hover-lang"],
	data() {
		const stored = this.loadStoredIds();
		const ids = stored !== null
			? this.nodes.filter((n) => stored.includes(n.id)).map((n) => n.id)
			: this.nodes.map((n) => n.id);
		return {
			openIds: new Set(ids),
		};
	},
	methods: {
		flatIndex(nodeIndex) {
			let index = this.startIndex;
			for (let i = 0; i < nodeIndex; i++) {
				index += 1 + this.visibleDescendants(this.nodes[i]);
			}
			return index;
		},
		visibleDescendants(node) {
			if (!this.hasChildren(node) || !this.isOpen(node.id)) return 0;
			let count = 0;
			for (const child of node.children) {
				count += 1 + this.visibleDescendants(child);
			}
			return count;
		},
		childStartIndex(nodeIndex) {
			return this.flatIndex(nodeIndex) + 1;
		},
		hasChildren(node) {
			return node.children && node.children.length > 0;
		},
		isOpen(id) {
			return this.openIds.has(id);
		},
		toggle(id) {
			if (this.openIds.has(id)) {
				this.openIds.delete(id);
			} else {
				this.openIds.add(id);
			}
			this.openIds = new Set(this.openIds);
			this.saveStoredIds();
		},
		loadStoredIds() {
			try {
				const raw = localStorage.getItem("translation-tree-open");
				return raw ? JSON.parse(raw) : null;
			} catch {
				return null;
			}
		},
		saveStoredIds() {
			const all = this.loadStoredIds() || [];
			for (const node of this.nodes) {
				const index = all.indexOf(node.id);
				if (this.openIds.has(node.id) && index === -1) {
					all.push(node.id);
				} else if (!this.openIds.has(node.id) && index !== -1) {
					all.splice(index, 1);
				}
			}
			localStorage.setItem("translation-tree-open", JSON.stringify(all));
		},
		percentLink(node, langCode) {
			if (node.id === "_variables") return "/languages/" + langCode;
			if (!node.link) return false;
			return "/" + node.link + "?language=" + langCode;
		},
		percentage(node, langCode) {
			const info = node.langs?.[langCode];
			if (!info || !info.total) return 0;
			return Math.round((info.translated / info.total) * 100);
		},
		langStatus(node, langCode) {
			return node.langs?.[langCode]?.status ?? "missing";
		},
		langTitle(node, langCode) {
			const info = node.langs?.[langCode];
			if (!info) return this.$t("translation-progress.missing");
			const labels = {
				complete: this.$t("translation-progress.complete"),
				partial: `${info.translated}/${info.total}`,
				untranslated: this.$t("translation-progress.untranslated"),
				missing: this.$t("translation-progress.missing"),
			};
			return labels[info.status] || info.status;
		},
	},
};
</script>

<style>
.translation-tree {
	list-style: none;
	padding: 0;
	margin: 0;
}

.translation-tree__row {
	display: flex;
	align-items: center;
}
.translation-tree__row--odd {
	background: light-dark(rgba(0, 0, 0, 0.025), rgba(255, 255, 255, 0.025));
}
.translation-tree__row:hover,
.translation-tree__row:focus-within {
	background: color-mix(in srgb, var(--color-blue-500), transparent 88%);
}

.translation-tree__branch {
	display: flex;
	align-items: center;
	flex: 1;
	min-width: 12rem;
	padding-inline-start: calc(var(--translation-tree-level) * 0.6rem);
}

.translation-tree__toggle {
	--icon-size: 12px;
	width: 1rem;
	aspect-ratio: 1/1;
	display: grid;
	place-items: center;
	padding: 0;
	border: none;
	background: none;
	border-radius: var(--rounded-sm);
	margin-inline-start: 0.25rem;
	flex-shrink: 0;
	color: inherit;
	cursor: pointer;
}
.translation-tree__toggle:hover {
	background: rgba(0, 0, 0, 0.075);
}

.translation-tree__folder {
	display: flex;
	height: var(--height-sm);
	border-radius: var(--rounded-sm);
	padding-inline: 0.25rem;
	width: 100%;
	align-items: center;
	gap: 0.325rem;
	min-width: 3rem;
	line-height: 1.25;
	font-size: var(--text-sm);
	color: var(--color-text);
	text-decoration: none;
}

.translation-tree__status {
	--icon-size: 15px;
	flex-shrink: 0;
}
.translation-tree__status--draft { color: var(--color-red-500); }
.translation-tree__status--unlisted { color: var(--color-blue-500); }
.translation-tree__status--listed { color: var(--color-green-500); }

.translation-tree__label {
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.translation-tree__percentages {
	display: flex;
	flex-shrink: 0;
	align-self: stretch;
	align-items: stretch;
}

.translation-tree__percent-cell {
	display: flex;
	align-items: center;
}

.translation-tree__percent {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 2.75rem;
	margin: 0.125rem 0.25rem;
	text-align: center;
	font-size: var(--text-xs);
	font-family: var(--font-mono);
	font-variant-numeric: tabular-nums;
	border: none;
	padding: 0.2rem 0.375rem;
	border-radius: var(--rounded);
	color: var(--color-text);
	background: color-mix(in srgb, var(--color-gray-500), transparent 90%);
}
.translation-tree__percent--complete {
	background: color-mix(in srgb, var(--color-green-500), transparent 60%);
}
.translation-tree__percent--partial {
	background: color-mix(in srgb, var(--color-yellow-500), transparent 55%);
}
.translation-tree__percent--untranslated,
.translation-tree__percent--missing {
	background: color-mix(in srgb, var(--color-gray-500), transparent 92%);
	opacity: 0.5;
}
.translation-tree__percent--empty {
	visibility: hidden;
}

/* Column hover */
.translation-tree__percent-cell--hover {
	background: color-mix(in srgb, var(--color-blue-500), transparent 88%);
}
</style>
