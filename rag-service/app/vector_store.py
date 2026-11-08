import uuid

from qdrant_client import QdrantClient
from qdrant_client.models import (
    Distance,
    FieldCondition,
    Filter,
    FilterSelector,
    MatchAny,
    MatchValue,
    PayloadSchemaType,
    PointIdsList,
    PointStruct,
    VectorParams,
)

from .config import settings

CHUNK_COLLECTION = "chunk_tai_lieu"
QUESTION_COLLECTION = "cau_hoi"


def get_qdrant_client() -> QdrantClient:
    return QdrantClient(
        url=settings.qdrant_url,
        api_key=settings.qdrant_api_key or None,
    )


def ensure_collections(vector_size: int = 1024) -> None:
    """Tạo các collection nếu chưa có. Vector dense của BGE-M3 có 1024 chiều."""
    client = get_qdrant_client()
    existing = {item.name for item in client.get_collections().collections}
    for name in (CHUNK_COLLECTION, QUESTION_COLLECTION):
        if name not in existing:
            client.create_collection(
                collection_name=name,
                vectors_config=VectorParams(size=vector_size, distance=Distance.COSINE),
            )
    # Chỉ mục để lọc nhanh theo tài liệu / học phần
    for collection, field, schema in (
        (CHUNK_COLLECTION, "document_id", PayloadSchemaType.INTEGER),
        (QUESTION_COLLECTION, "ma_cau_hoi", PayloadSchemaType.INTEGER),
        (QUESTION_COLLECTION, "ma_hoc_phan", PayloadSchemaType.KEYWORD),
    ):
        try:
            client.create_payload_index(collection, field, schema)
        except Exception:
            pass  # đã tồn tại


def point_id(document_id: int, chunk_index: int) -> str:
    """ID cố định theo (tài liệu, số thứ tự đoạn) -> nạp lại cùng tài liệu sẽ ghi đè."""
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"tai-lieu:{document_id}:{chunk_index}"))


def delete_document_chunks(document_id: int) -> None:
    get_qdrant_client().delete(
        collection_name=CHUNK_COLLECTION,
        points_selector=FilterSelector(
            filter=Filter(must=[FieldCondition(key="document_id", match=MatchValue(value=document_id))])
        ),
    )


def upsert_chunks(
    document_id: int,
    chunks: list[tuple[int, str]],
    vectors: list[list[float]],
    model_name: str,
) -> list[str]:
    points = []
    ids = []
    for index, ((page, text), vector) in enumerate(zip(chunks, vectors)):
        pid = point_id(document_id, index)
        ids.append(pid)
        points.append(
            PointStruct(
                id=pid,
                vector=vector,
                payload={
                    "document_id": document_id,
                    "chunk_index": index,
                    "page": page,
                    "text": text,
                    "model": model_name,
                },
            )
        )
    get_qdrant_client().upsert(collection_name=CHUNK_COLLECTION, points=points)
    return ids


def search_chunks(vector: list[float], limit: int, document_ids: list[int] | None = None) -> list[dict]:
    query_filter = None
    if document_ids:
        query_filter = Filter(must=[FieldCondition(key="document_id", match=MatchAny(any=document_ids))])
    response = get_qdrant_client().query_points(
        collection_name=CHUNK_COLLECTION,
        query=vector,
        limit=limit,
        query_filter=query_filter,
        with_payload=True,
    )
    return [{"score": p.score, **(p.payload or {})} for p in response.points]


# ---------- Vector câu hỏi (collection cau_hoi) phục vụ phát hiện trùng lặp ----------
def question_point_id(ma_cau_hoi: int) -> str:
    return str(uuid.uuid5(uuid.NAMESPACE_URL, f"cau-hoi:{ma_cau_hoi}"))


def upsert_question(ma_cau_hoi: int, ma_hoc_phan: str, vector: list[float], model_name: str) -> str:
    pid = question_point_id(ma_cau_hoi)
    get_qdrant_client().upsert(
        collection_name=QUESTION_COLLECTION,
        points=[
            PointStruct(
                id=pid,
                vector=vector,
                payload={"ma_cau_hoi": ma_cau_hoi, "ma_hoc_phan": ma_hoc_phan, "model": model_name},
            )
        ],
    )
    return pid


def search_questions(vector: list[float], limit: int, ma_hoc_phan: str, exclude_id: int) -> list[dict]:
    """Tìm các câu hỏi gần nghĩa nhất trong cùng học phần, bỏ qua chính câu đang kiểm tra."""
    response = get_qdrant_client().query_points(
        collection_name=QUESTION_COLLECTION,
        query=vector,
        limit=limit,
        query_filter=Filter(
            must=[FieldCondition(key="ma_hoc_phan", match=MatchValue(value=ma_hoc_phan))],
            must_not=[FieldCondition(key="ma_cau_hoi", match=MatchValue(value=exclude_id))],
        ),
        with_payload=True,
    )
    return [{"ma_cau_hoi": p.payload["ma_cau_hoi"], "score": p.score} for p in response.points]


def delete_questions(ma_cau_hois: list[int]) -> None:
    get_qdrant_client().delete(
        collection_name=QUESTION_COLLECTION,
        points_selector=PointIdsList(points=[question_point_id(m) for m in ma_cau_hois]),
    )
