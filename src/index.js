import "./index.css"

import FileUsage from "./sections/file-usage.vue"

panel.plugin("tobimori/file-usage", {
	sections: {
		"file-usage": FileUsage
	}
})
