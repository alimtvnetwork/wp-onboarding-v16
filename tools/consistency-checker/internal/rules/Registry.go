// Package rules — rule registration.
package rules

import "consistency-checker/internal/engine"

// RegisterAll adds all built-in rules to the engine.
func RegisterAll(eng *engine.Engine) {
	eng.Register(&GoFileSize{})
	eng.Register(&GoFuncSize{})
	eng.Register(&GoSingleReturn{})
	eng.Register(&GoImportGroups{})
	eng.Register(&GoParamCount{})
	eng.Register(&GoInlineIf{})
	eng.Register(&GoAbbrCasing{})
	eng.Register(&GoRawError{})
	eng.Register(&FileNaming{})
	eng.Register(&PhpFileSize{})
	eng.Register(&PhpFuncSize{})
	eng.Register(&PhpImportGroups{})
	eng.Register(&MdHeading{})
}
