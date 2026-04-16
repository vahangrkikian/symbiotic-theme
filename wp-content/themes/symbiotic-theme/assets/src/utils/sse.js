/**
 * SSE stream parser for ReadableStream responses.
 */
export function parseSSEChunk(chunk) {
  const events = [];
  const parts  = chunk.split('\n\n');
  for (const part of parts) {
    if (!part.trim()) continue;
    const lines = part.split('\n');
    let event   = 'message';
    let dataStr = '';
    for (const line of lines) {
      if (line.startsWith('event: ')) event   = line.slice(7).trim();
      if (line.startsWith('data: '))  dataStr = line.slice(6).trim();
    }
    if (dataStr) {
      try {
        events.push({ event, data: JSON.parse(dataStr) });
      } catch (e) { /* skip malformed */ }
    }
  }
  return events;
}

/**
 * Read a streaming Response and dispatch to handlers.
 *
 * @param {Response} response - Fetch Response object
 * @param {{ onToken, onAttachments, onDone, onError, onStatus }} handlers
 */
export async function readStream(response, handlers = {}) {
  const reader  = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer    = '';

  try {
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });
      const splitPoint = buffer.lastIndexOf('\n\n');
      if (splitPoint === -1) continue;

      const toProcess = buffer.slice(0, splitPoint + 2);
      buffer          = buffer.slice(splitPoint + 2);

      const events = parseSSEChunk(toProcess);
      for (const { event, data } of events) {
        switch (event) {
          case 'status':       handlers.onStatus?.(data);      break;
          case 'token':        handlers.onToken?.(data.text);  break;
          case 'attachments':  handlers.onAttachments?.(data.attachments); break;
          case 'done':         handlers.onDone?.(data);        break;
          case 'error':        handlers.onError?.(data);       break;
        }
      }
    }
  } catch (err) {
    handlers.onError?.({ message: err.message });
  }
}
