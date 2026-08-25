export default function ExampleIsland({ message = 'Hello from a Spinx island!' }) {
  return (
    <div className="spinx-example-island">
      <p>{message}</p>
      <p><small>Server-rendered markup, client-hydrated component — this time with React instead of Vue, same @island directive on the backend.</small></p>
    </div>
  )
}
