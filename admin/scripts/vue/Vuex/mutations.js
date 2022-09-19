export function updateState(currentState, params)
{
    Object.keys(params)
          .map(key => currentState[key] = params[key]);
}
