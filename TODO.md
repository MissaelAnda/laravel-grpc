# TODO

- [ ] Wrap services so they are resolved from sandbox container on each service call instead of being cached (Prevent cache resolved / mutated instances from previous requests).
- [ ] Implement the `grpc:protos` generate command to use `protoc` and organize correctly the generated code
- [ ] Move Application creation to an ApplicationFactory class to have cleaner code
- [ ] Create a GrpcKernel with the `bootstrap`, `handle` and `terminate` methods so the user can add custom callbacks and manage execution time with `commandLifecycleDurationHandlers` (`serviceCallStartedAt` and `serviceCallFinishedAt`)
- [ ] Add events:
  - WorkerStarting
  - WorkerError
  - WorkerStopping
  - ServiceCallReceived
  - ServiceCallHandled
  - etc.
- Add callbacks for `flushService`, and `terminateInterceptor`
- Only register needed service providers for gRPC but let the user add them (Blade,Vite,etc... are not needed)
