import "./index.css"

import FileUsage from "./fields/file-usage.vue"

panel.plugin("tobimori/file-usage", {
	fields: {
		"file-usage": FileUsage
	}
})
