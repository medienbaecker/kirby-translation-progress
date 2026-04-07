import LanguagesView from "./LanguagesView.vue"
import TranslationTree from "./TranslationTree.vue"

panel.plugin("medienbaecker/translation-status", {
	components: {
		"k-languages-view": LanguagesView,
		"translation-tree": TranslationTree,
	},
})
