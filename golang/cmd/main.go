package main

import (
	"nodasoft/internal/lib"
	"sync"
	"time"
)

// ЗАДАНИЕ:
// * сделать из плохого кода хороший;
// * важно сохранить логику появления ошибочных тасков;
// * сделать правильную мультипоточность обработки заданий.
// Обновленный код отправить через merge-request.

// приложение эмулирует получение и обработку тасков, пытается и получать и обрабатывать в многопоточном режиме
// В конце должно выводить успешные таски и ошибки выполнены остальных тасков

func main() {
	// В целом напоминает шаблон worker pool

	// Сгруппировал переменные
	var superChan chan lib.Task = make(chan lib.Task, 10)
	var doneTasks chan lib.Task = make(chan lib.Task)
	var undoneTasks chan error = make(chan error)

	// Генерируем таски
	go func() {
		defer close(superChan)
		createTasksWithTimeout(3, superChan)
	}()

	// Исполняем таски
	go func() {
		defer close(doneTasks)
		defer close(undoneTasks)
		execTasks(superChan, doneTasks, undoneTasks)
	}()

	// Парсим результаты в переменные основного потока с ожиданием через wait group
	result, errors := parseResult(doneTasks, undoneTasks)

	println("Errors:")
	for r := range errors {
		println(r)
	}

	println("Done tasks:")
	for r := range result {
		println(r)
	}
}

func createTask() lib.Task {
	var job lib.Job = func() bool { return true }
	if time.Now().Nanosecond()%2 > 0 { // вот такое условие появления ошибочных тасков
		job = func() bool { return false }
	}
	return lib.CreateTask(job) // передаем таск на выполнение
}

func createTasksWithTimeout(timeout time.Duration, ch chan lib.Task) {
	for start := time.Now(); time.Since(start) < time.Second*timeout; {
		task := createTask()
		ch <- task
	}
}

func execTasks(data chan lib.Task, done chan lib.Task, undone chan error) {
	for t := range data {
		t.Exec(done, undone)
	}
}

func parseResult(suc chan lib.Task, undone chan error) (map[int]lib.Task, []error) {
	var result map[int]lib.Task = make(map[int]lib.Task)
	var err []error

	var wg sync.WaitGroup
	wg.Add(2)

	go func() {
		defer wg.Done()
		for r := range suc {
			result[r.GetId()] = r
		}
	}()
	go func() {
		defer wg.Done()
		for r := range undone {
			err = append(err, r)
		}
	}()

	wg.Wait()

	return result, err
}
