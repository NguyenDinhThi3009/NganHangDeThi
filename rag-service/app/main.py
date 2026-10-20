from fastapi import FastAPI
from .config import settings
from .schemas import HealthResponse, SearchRequest, SearchResponse

app = FastAPI(
    title=settings.app_name,
    version="0.1.0",
    description="Starter API for document retrieval and RAG workflows.",
)


@app.get("/health", response_model=HealthResponse)
def health() -> HealthResponse:
    return HealthResponse()


@app.get("/")
def root() -> dict[str, str]:
    return {
        "service": "nganhangdethi-rag",
        "docs": "/docs",
        "status": "starter",
    }


@app.post("/v1/search", response_model=SearchResponse)
def search(request: SearchRequest) -> SearchResponse:
    # TODO: implement query embedding, Qdrant search, and MySQL hydration.
    # Keep canonical text and metadata in MySQL; use Qdrant for vector retrieval.
    return SearchResponse(results=[])
