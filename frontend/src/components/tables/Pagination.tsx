interface PaginationProps {
    page: number;
    lastPage: number;
    total: number;
    onChange: (page: number) => void;
}

export function Pagination({
    page,
    lastPage,
    total,
    onChange,
}: PaginationProps) {
    if (lastPage <= 1)
        return <div className="pagination muted">{total} data</div>;

    return (
        <div className="pagination">
            <span className="muted">{total} data</span>
            <div className="pagination-actions">
                <button
                    type="button"
                    className="btn btn-ghost"
                    disabled={page <= 1}
                    onClick={() => onChange(page - 1)}
                >
                    Sebelumnya
                </button>
                <span>
                    {page} / {lastPage}
                </span>
                <button
                    type="button"
                    className="btn btn-ghost"
                    disabled={page >= lastPage}
                    onClick={() => onChange(page + 1)}
                >
                    Berikutnya
                </button>
            </div>
        </div>
    );
}
