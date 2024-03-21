package lib

type TaskResult struct {
	message string
}

type TaskResultProcessor interface {
	GetMessage() string
}

type Success struct {
	TaskResult
}

type Fail struct {
	TaskResult
}

func (t Success) GetMessage() string {
	return t.message
}

func (t Fail) GetMessage() string {
	return t.message
}
