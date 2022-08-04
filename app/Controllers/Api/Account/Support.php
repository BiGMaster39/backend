<?php namespace App\Controllers\Api\Account;

use App\Controllers\PrivateController;
use App\Models\SupportTicketsModel;
use App\Models\SupportCommentsModel;
use App\Libraries\Uid;
use ReflectionException;

define("LIMIT", 20);

class Support extends PrivateController
{
    private $tickets;
    private $comments;
    private $uid;

    /**
     * Create models, config and library's
     */
    function __construct()
    {
        $this->tickets = new SupportTicketsModel();
        $this->comments = new SupportCommentsModel();
        $this->uid = new Uid();
    }

    /**************************************************************************************
     * PUBLIC FUNCTIONS
     **************************************************************************************/

    /**
     * Get list tickets
     * @param int $sort
     * 0 - pending
     * 1 - archive
     * @param int $offset
     * @return mixed
     */
    public function list(int $sort = 0, int $offset = 0)
    {
        if (!$sort) {
            $where = ["status <" => 2, "user_id" => $this->user["id"]];
        } else {
            $where = ["status" => 2, "user_id" => $this->user["id"]];
        }
        $tickets = $this->tickets
            ->where($where)
            ->orderBy("updated_at", "DESC")
            ->findAll(LIMIT, (int) $offset);
        $count = $this->tickets
            ->where($where)
            ->countAllResults();
        $list = [];
        foreach ($tickets as $ticket) {
            $last_comment = $this->comments
                ->where("ticket_id", $ticket["id"])
                ->select("message,created_at")
                ->orderBy("id", "DESC")
                ->first();
            $list[] = [
                "uid"     => $ticket["uid"],
                "title"   => $ticket["title"],
                "status"  => (int) $ticket["status"],
                "updated" => date('d-m-Y H:i', $ticket['updated_at']),
                "message" => [
                    "comment" => $last_comment["message"],
                    "created" => date('d-m-Y', $last_comment['created_at']),
                ]
            ];
        }
        return $this->respond([
            "list"  => $list,
            "count" => $count
        ], 200);
    }

    /**
     * Get list comments
     * @param string $uid
     * @return mixed
     */
    public function comments(string $uid = "")
    {
        $ticket = $this->tickets
            ->where(["user_id" => $this->user["id"], "uid" => esc($uid)])
            ->select("id,title,status,uid")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        $comments = $this->comments
            ->where("ticket_id", $ticket["id"])
            ->orderBy("id", "ASC")
            ->findAll();
        $list = [];
        foreach ($comments as $comment) {
            $list[] = [
                "uid"        => $comment["uid"],
                "message"    => $comment["message"],
                "created"    => date('d-m-Y H:i', $comment['created_at']),
                "estimation" => (int) $comment["estimation"],
                "admin"      => !$comment["user_id"],
                "loading"    => false
            ];
        }
        return $this->respond([
            "list"   => $list,
            "ticket" => [
                "title"  => $ticket["title"],
                "uid"    => $ticket["uid"],
                "status" => (int) $ticket["status"]
            ]
        ], 200);
    }

    /**
     * Create new ticket
     * @return mixed
     * @throws ReflectionException
     */
    public function create_ticket()
    {
        if (!$this->validate($this->create_ticket_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $uid = $this->uid->create();
        $ticket_id = $this->tickets->insert([
            "title"   => esc($this->request->getPost("title")),
            "user_id" => $this->user["id"],
            "status"  => 0,
            "uid"     => $uid
        ]);
        $this->comments->insert([
            "user_id"    => $this->user["id"],
            "message"    => esc($this->request->getPost("message")),
            "estimation" => 0,
            "uid"        => $this->uid->create(),
            "ticket_id"  => $ticket_id
        ]);
        return $this->respond([
            "code"    => 200,
            "uid"     => $uid,
            "created" => date('d-m-Y H:i')
        ], 200);
    }

    /**
     * Create new comment
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function create_comment(string $uid = "")
    {
        if (!$this->validate($this->create_comment_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $ticket = $this->tickets
            ->where(["user_id" => $this->user["id"], "uid" => esc($uid)])
            ->select("id,status")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        if ($ticket["status"] == 2) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_50")
                ],
            ], 400);
        }
        $comment_uid = $this->uid->create();
        $this->comments->insert([
            "user_id"    => $this->user["id"],
            "message"    => esc($this->request->getPost("message")),
            "estimation" => 0,
            "uid"        => $comment_uid,
            "ticket_id"  => $ticket["id"]
        ]);
        $this->tickets->update($ticket["id"], [
            "status" => 0
        ]);
        return $this->respond([
            "code"    => 200,
            "uid"     => $comment_uid,
            "created" => date('d-m-Y H:i')
        ], 200);
    }

    /**
     * Change ticket status
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function change_status(string $uid = "")
    {
        $ticket = $this->tickets
            ->where(["user_id" => $this->user["id"], "uid" => esc($uid)])
            ->select("id,status")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        $this->tickets->update($ticket["id"], [
            "status" => $ticket["status"] == 2 ? 0 : 2
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**
     * Change rate
     * @param string $uid
     * @return mixed
     * @throws ReflectionException
     */
    public function change_rate(string $uid = "")
    {
        if (!$this->validate($this->rate_validation_type())) {
            return $this->respond([
                "code"    => 400,
                "message" => $this->validator->getErrors(),
            ], 400);
        }
        $comment = $this->comments
            ->where(["uid" => esc($uid)])
            ->select("uid,ticket_id,id")
            ->first();
        if (!$comment) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_51")
                ],
            ], 400);
        }
        $ticket = $this->tickets
            ->where(["user_id" => $this->user["id"], "id" => $comment["ticket_id"]])
            ->select("id")
            ->first();
        if (!$ticket) {
            return $this->respond([
                "code"    => 400,
                "message" => [
                    "error" => lang("Message.message_49")
                ],
            ], 400);
        }
        $this->comments->update($comment["id"], [
            "estimation" => (int) $this->request->getPost("estimation")
        ]);
        return $this->respond(["code" => 200], 200);
    }

    /**************************************************************************************
     * PRIVATE FUNCTIONS
     **************************************************************************************/

    /**
     * Get validation rules for create new ticket
     * @return array
     */
    private function create_ticket_validation_type(): array
    {
        return [
            "title"   => ["label" => lang("Fields.field_78"),  "rules" => "required|min_length[3]|max_length[100]"],
            "message" => ["label" => lang("Fields.field_79"),  "rules" => "required|min_length[2]|max_length[1000]"],
        ];
    }

    /**
     * Get validation rules for create new comment
     * @return array
     */
    private function create_comment_validation_type(): array
    {
        return [
            "message" => ["label" => lang("Fields.field_79"),  "rules" => "required|min_length[2]|max_length[1000]"],
        ];
    }

    /**
     * Get validation rules for set comment rate
     * @return array
     */
    private function rate_validation_type(): array
    {
        return [
            "estimation" => ["label" => lang("Fields.field_80"),  "rules" => "required|in_list[1,2,3]"],
        ];
    }

}