from fastapi import FastAPI
import time

app = FastAPI()

@app.post("/ask")
async def ask(query: str):
    start_time = time.time()
    # Simulate processing time
    response = {"answer": f"Response to {query}"}
    end_time = time.time()
    print(f"Inference time: {end_time - start_time} seconds")
    return response
