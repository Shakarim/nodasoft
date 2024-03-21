package lib

import (
	"fmt"
	"time"
)

type Job func() bool

type Task struct {
	id     int
	cT     time.Time // время создания
	fT     time.Time // время выполнения
	result TaskResultProcessor
	job    Job
}

func CreateTask(job Job) Task {
	var ct time.Time = time.Now()
	return Task{id: int(ct.Unix()), cT: ct, job: job}
}

type TaskProcessor interface {
	GetId() int
	Exec(success chan Task, failure chan error) *Task
}

func (task *Task) GetId() int { return task.id }
func (task *Task) Exec(success chan Task, failure chan error) *Task {
	if task.job() == true && task.cT.After(time.Now().Add(-20*time.Second)) {
		task.result = Success{TaskResult{message: "task has been succeed"}}
		success <- *task
	} else {
		task.result = Fail{TaskResult{message: "something went wrong"}}
		failure <- fmt.Errorf("task id %d time %s; err=%s", task.id, task.cT, task.result.GetMessage())
	}
	task.fT = time.Now()

	time.Sleep(time.Millisecond * 150)

	return task
}
