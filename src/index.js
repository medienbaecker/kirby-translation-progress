import LanguagesView from "./LanguagesView.vue"
import TranslationTree from "./TranslationTree.vue"

panel.plugin("medienbaecker/translation-progress", {
	components: {
		"k-languages-view": LanguagesView,
		"translation-tree": TranslationTree,
	},
})
